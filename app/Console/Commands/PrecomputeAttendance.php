<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Nightly job: pre-compute attendance for the last 2 months and store
 * per-employee / per-day rows in `attendance_cache`.
 *
 * Schedule: daily at 20:00  (set in app/Console/Kernel.php)
 * Run manually: php artisan attendance:precompute
 */
class PrecomputeAttendance extends Command
{
    protected $signature   = 'attendance:precompute {--force : Re-compute even if today\'s cache already exists}';
    protected $description = 'Pre-compute attendance data for the last 2 months and store in the attendance_cache table';

    private const CATEGORIES = ['personnel', 'doctorants', 'complex', 'vacataire_emadu'];
    private const CACHE_DAYS  = 61; // how many days back to compute

    // -------------------------------------------------------------------------

    public function handle(): int
    {
        $endDate   = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-' . self::CACHE_DAYS . ' days'));

        // Skip if today's cache already exists (unless --force)
        if (!$this->option('force')) {
            $alreadyDone = DB::table('attendance_cache')
                ->where('computed_at', '>=', date('Y-m-d') . ' 00:00:00')
                ->exists();

            if ($alreadyDone) {
                $this->info('Cache already computed today. Use --force to re-run.');
                return 0;
            }
        }

        $python = $this->detectPython();
        if (!$python) {
            $this->error('Python not found. Set PYTHON_COMMAND in .env or install Python 3 and add it to PATH.');
            return 1;
        }

        $this->info("Pre-computing attendance: {$startDate} → {$endDate}");

        foreach (self::CATEGORIES as $category) {
            $this->info("  [{$category}] starting...");
            $this->processCategory($category, $startDate, $endDate, $python);
        }

        $this->info('Pre-computation complete.');
        return 0;
    }

    // -------------------------------------------------------------------------
    // Per-category processing
    // -------------------------------------------------------------------------

    private function processCategory(string $category, string $startDate, string $endDate, string $python): void
    {
        try {
            DB::statement("SET collation_connection = 'utf8_general_ci'");

            // 1. Fetch raw punch records from BioStar
            $records = $this->fetchPunchRecords($category, $startDate . ' 00:00:00', $endDate . ' 23:59:59');

            // 2. Fetch the full employee list (for zero-punch employees)
            $allEmployees = $this->fetchEmployees($category);

            // 3. Fetch public holidays
            $publicHolidays = DB::table('public_holidays')
                ->pluck('holiday_name', 'holiday_date')
                ->mapWithKeys(fn($name, $date) => [(string) $date => $name])
                ->toArray();

            // 4. Build the JSON payload for the Python script
            $payload = [
                'records'         => $records,
                'all_employees'   => $allEmployees,
                'start_date'      => $startDate,
                'end_date'        => $endDate,
                'public_holidays' => $publicHolidays,
            ];

            $jsonData = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($jsonData === false) {
                $this->error("    JSON encoding failed: " . json_last_error_msg());
                return;
            }

            // 5. Write payload to temp file and call Python
            $scriptName = in_array($category, ['complex', 'vacataire_emadu'])
                ? 'attendance_processor1.py'
                : 'ramadan.py';
            $scriptPath = base_path('scripts\\' . $scriptName);
            $tempFile   = storage_path("app\\temp_precompute_{$category}.json");

            if (!is_dir(dirname($tempFile))) {
                mkdir(dirname($tempFile), 0755, true);
            }

            file_put_contents($tempFile, $jsonData);
            putenv('PYTHONIOENCODING=utf-8');

            $cmd = "$python -X utf8 \"$scriptPath\" "
                . escapeshellarg($tempFile)
                . ' ' . escapeshellarg($startDate)
                . ' ' . escapeshellarg($endDate)
                . ' 2>&1';

            $output     = [];
            $returnCode = 0;
            exec($cmd, $output, $returnCode);

            @unlink($tempFile);

            if ($returnCode !== 0) {
                $errMsg = implode("\n", array_slice($output, 0, 20));
                $this->error("    Python failed (code {$returnCode}): {$errMsg}");
                Log::error("PrecomputeAttendance [{$category}] Python error: {$errMsg}");
                return;
            }

            // 6. Parse output
            $rawOutput = implode("\n", $output);
            $rawOutput = preg_replace('/^\xEF\xBB\xBF/', '', $rawOutput);   // strip BOM
            $rawOutput = preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', $rawOutput);

            $parsed = json_decode($rawOutput, true);
            if (!$parsed || !isset($parsed['processed_data'])) {
                $this->error("    Invalid JSON output from Python");
                Log::error("PrecomputeAttendance [{$category}] bad output: " . mb_substr($rawOutput, 0, 500));
                return;
            }

            // 7. Upsert rows into attendance_cache
            $this->upsertCache($category, $parsed['processed_data']);
            $count = count($parsed['processed_data']);
            $this->info("    [{$category}] saved {$count} rows.");

        } catch (\Throwable $e) {
            $this->error("    Exception for [{$category}]: " . $e->getMessage());
            Log::error("PrecomputeAttendance [{$category}]: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }

    // -------------------------------------------------------------------------
    // Cache upsert
    // -------------------------------------------------------------------------

    private function upsertCache(string $category, array $processedData): void
    {
        $now  = now()->toDateTimeString();
        $rows = [];

        foreach ($processedData as $record) {
            $date  = $record['Date'] ?? null;
            $empId = (string) ($record['ID utilisateur'] ?? '');
            if (!$date || !$empId) {
                continue;
            }
            $rows[] = [
                'category'    => $category,
                'work_date'   => $date,
                'employee_id' => $empId,
                'row_json'    => json_encode($record, JSON_UNESCAPED_UNICODE),
                'computed_at' => $now,
            ];
        }

        // Batch upsert in chunks of 500 to avoid max_allowed_packet issues
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('attendance_cache')->upsert(
                $chunk,
                ['category', 'work_date', 'employee_id'],   // unique keys
                ['row_json', 'computed_at']                  // columns to update on conflict
            );
        }
    }

    // -------------------------------------------------------------------------
    // BioStar queries (mirror of AttendanceController, without group/person filter)
    // -------------------------------------------------------------------------

    private function fetchPunchRecords(string $category, string $startDate, string $endDate): array
    {
        // 4 date pairs (one per UNION clause)
        $params = [$startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate];

        $groupFilter = $this->buildGroupFilter($category);
        if ($groupFilter === null) {
            return [];
        }

        $devIds = "546271448, 546271450, 546271459, 546271456,
                   546271451, 546271446, 546271441, 546271465,
                   546271439, 546271455, 546271457, 546271449";

        $results = DB::connection('biostar_tna')->select("
            SELECT DATE_ADD(l.devdt, INTERVAL 1 HOUR) AS srvdt,
                   CAST(l.devid AS CHAR) AS devuid,
                   CAST(u.usrid AS CHAR) AS usrid,
                   CAST(u.nm AS CHAR) AS user_name,
                   CAST(g.nm AS CHAR) AS group_name,
                   'Original' AS record_type
            FROM punchlog l
            JOIN biostar2_ac.t_usr u ON l.user_id COLLATE utf8_general_ci = u.usrid COLLATE utf8_general_ci
            JOIN biostar2_ac.t_usrgr g ON u.usrgruid = g.usrgruid
            WHERE {$groupFilter}
              AND l.devid IN ({$devIds})
              AND l.bsevtc = 4102
              AND l.devdt BETWEEN ? AND ?

            UNION ALL

            SELECT DATE_ADD(device_datetime, INTERVAL 1 HOUR) AS srvdt,
                   CAST(type AS CHAR) AS devuid,
                   CAST(USER_ID AS CHAR) AS usrid,
                   CAST(user_name AS CHAR) AS user_name,
                   CAST(g.nm AS CHAR) AS group_name,
                   'Modified' AS record_type
            FROM modifiedpunchlog
            JOIN biostar2_ac.t_usr u ON USER_ID COLLATE utf8_general_ci = u.usrid COLLATE utf8_general_ci
            JOIN biostar2_ac.t_usrgr g ON u.usrgruid = g.usrgruid
            WHERE {$groupFilter}
              AND device_datetime BETWEEN ? AND ?

            UNION ALL

            SELECT DATE_ADD(start_date, INTERVAL 1 HOUR) AS srvdt,
                   'PUNCH_TYPE_BREAK_END' AS devuid,
                   CAST(l.user_id AS CHAR) AS usrid,
                   CAST(u.nm AS CHAR) AS user_name,
                   CAST(g.nm AS CHAR) AS group_name,
                   CAST(p.name AS CHAR) AS record_type
            FROM biostar_tna.leave l
            JOIN biostar2_ac.t_usr u ON l.user_id COLLATE utf8_general_ci = u.usrid COLLATE utf8_general_ci
            JOIN biostar2_ac.t_usrgr g ON u.usrgruid = g.usrgruid
            JOIN paycode p ON p.id = l.pcid
            WHERE {$groupFilter}
              AND start_date BETWEEN ? AND ?

            UNION ALL

            SELECT DATE_ADD(end_date, INTERVAL 1 HOUR) AS srvdt,
                   'PUNCH_TYPE_BREAK_START' AS devuid,
                   CAST(l.user_id AS CHAR) AS usrid,
                   CAST(u.nm AS CHAR) AS user_name,
                   CAST(g.nm AS CHAR) AS group_name,
                   CAST(p.name AS CHAR) AS record_type
            FROM biostar_tna.leave l
            JOIN biostar2_ac.t_usr u ON l.user_id COLLATE utf8_general_ci = u.usrid COLLATE utf8_general_ci
            JOIN biostar2_ac.t_usrgr g ON u.usrgruid = g.usrgruid
            JOIN paycode p ON p.id = l.pcid
            WHERE {$groupFilter}
              AND start_date BETWEEN ? AND ?
        ", $params);

        // Clean UTF-8 (same as controller)
        return array_map(function ($item) {
            return array_map(function ($v) {
                if (!is_string($v)) {
                    return $v;
                }
                $enc = mb_detect_encoding($v, ['UTF-8', 'ISO-8859-1', 'ASCII'], true) ?: 'ISO-8859-1';
                $v   = mb_convert_encoding($v, 'UTF-8', $enc);
                return preg_replace('/[\x00-\x1F\x7F]/u', '', $v);
            }, (array) $item);
        }, $results);
    }

    private function fetchEmployees(string $category): array
    {
        $groupFilter = $this->buildGroupFilter($category);
        if ($groupFilter === null) {
            return [];
        }

        $employees = DB::connection('biostar_tna')->select(
            "SELECT DISTINCT
                CAST(u.usrid AS CHAR) as user_id,
                CAST(u.nm AS CHAR) as user_name,
                CAST(g.nm AS CHAR) as department
             FROM biostar2_ac.t_usr u
             JOIN biostar2_ac.t_usrgr g ON u.usrgruid = g.usrgruid
             WHERE {$groupFilter}
             ORDER BY u.nm"
        );

        return array_map(fn($e) => (array) $e, $employees);
    }

    /**
     * Returns the SQL WHERE fragment for the given category, or null if unknown.
     * For vacataire_emadu the IDs are fetched inline from the local DB.
     */
    private function buildGroupFilter(string $category): ?string
    {
        if ($category === 'personnel') {
            return "g.usrgruid IN (
                SELECT usrgruid FROM biostar2_ac.t_usrgr WHERE parentusrgruid = 1002
                UNION
                SELECT g2.usrgruid FROM biostar2_ac.t_usrgr g1
                JOIN biostar2_ac.t_usrgr g2 ON g2.parentusrgruid = g1.usrgruid
                WHERE g1.parentusrgruid = 1002
            )";
        }

        if ($category === 'doctorants') {
            return "g.usrgruid IN (1004, 13100)";
        }

        if ($category === 'complex') {
            return "g.usrgruid = 1072";
        }

        if ($category === 'vacataire_emadu') {
            $eaduIds = DB::connection('mysql')
                ->table('emadu.vacataires_emadu')
                ->whereNotNull('id_biostar')
                ->pluck('id_biostar')
                ->map(fn($id) => (string) $id)
                ->unique()
                ->values()
                ->toArray();

            $idList = !empty($eaduIds) ? implode(',', array_map('intval', $eaduIds)) : '0';
            return "u.usrid IN ({$idList})";
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Python detection (same logic as AttendanceController)
    // -------------------------------------------------------------------------

    private function detectPython(): ?string
    {
        $envCommand = env('PYTHON_COMMAND');
        if ($envCommand) {
            return $envCommand;
        }

        $candidates = ['py -3.11', 'py -3', 'py', 'python3', 'python'];

        // Paths found by 'where python'
        $wherePython = [];
        exec('where python 2>&1', $wherePython, $whereReturn);
        if ($whereReturn === 0) {
            foreach ($wherePython as $foundPath) {
                $foundPath = trim($foundPath);
                if ($foundPath && file_exists($foundPath)) {
                    $candidates[] = '"' . $foundPath . '"';
                }
            }
        }

        // Common installation paths
        $user = get_current_user();
        foreach ([
            'C:\\Python313\\python.exe',
            'C:\\Python312\\python.exe',
            'C:\\Python311\\python.exe',
            'C:\\Python310\\python.exe',
            "C:\\Users\\{$user}\\AppData\\Local\\Programs\\Python\\Python313\\python.exe",
            "C:\\Users\\{$user}\\AppData\\Local\\Programs\\Python\\Python312\\python.exe",
            "C:\\Users\\{$user}\\AppData\\Local\\Programs\\Python\\Python311\\python.exe",
        ] as $path) {
            if (file_exists($path)) {
                $candidates[] = '"' . $path . '"';
            }
        }

        foreach ($candidates as $cmd) {
            $out = [];
            $ret = 0;
            exec("$cmd --version 2>&1", $out, $ret);
            if ($ret === 0) {
                return $cmd;
            }
        }

        return null;
    }
}
