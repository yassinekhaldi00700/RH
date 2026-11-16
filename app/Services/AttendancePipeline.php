<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Shared "fetch BioStar punches for a category/date range, run them through the
 * Python attendance script, return processed_data" pipeline. Extracted from
 * PrecomputeAttendance so other callers (e.g. the anomalie notification build
 * command) don't need a third copy of this logic.
 */
class AttendancePipeline
{
    /**
     * Compute attendance for one category over a date range.
     * Returns the Python script's `processed_data` array, or [] on failure
     * (errors are logged, never thrown, so a batch job can skip and continue).
     */
    public function computeForCategory(string $category, string $startDate, string $endDate): array
    {
        try {
            DB::statement("SET collation_connection = 'utf8_general_ci'");

            $python = $this->detectPython();
            if (!$python) {
                Log::error('AttendancePipeline: Python not found (set PYTHON_COMMAND in .env).');
                return [];
            }

            $records      = $this->fetchPunchRecords($category, $startDate . ' 00:00:00', $endDate . ' 23:59:59');
            $allEmployees = $this->fetchEmployees($category);
            $publicHolidays = DB::table('public_holidays')
                ->pluck('holiday_name', 'holiday_date')
                ->mapWithKeys(fn($name, $date) => [(string) $date => $name])
                ->toArray();

            $payload = [
                'records'         => $records,
                'all_employees'   => $allEmployees,
                'start_date'      => $startDate,
                'end_date'        => $endDate,
                'public_holidays' => $publicHolidays,
            ];

            $jsonData = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($jsonData === false) {
                Log::error('AttendancePipeline: JSON encoding failed: ' . json_last_error_msg());
                return [];
            }

            $scriptName = in_array($category, ['complex', 'vacataire_emadu'])
                ? 'attendance_processor1.py'
                : 'ramadan.py';
            $scriptPath = base_path('scripts\\' . $scriptName);
            $tempFile   = storage_path('app\\temp_pipeline_' . $category . '_' . uniqid() . '.json');

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
                Log::error("AttendancePipeline [{$category}] Python error: " . implode("\n", array_slice($output, 0, 20)));
                return [];
            }

            $rawOutput = implode("\n", $output);
            $rawOutput = preg_replace('/^\xEF\xBB\xBF/', '', $rawOutput);
            $rawOutput = preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', $rawOutput);

            $parsed = json_decode($rawOutput, true);
            if (!$parsed || !isset($parsed['processed_data'])) {
                Log::error("AttendancePipeline [{$category}] bad output: " . mb_substr($rawOutput, 0, 500));
                return [];
            }

            return $parsed['processed_data'];
        } catch (\Throwable $e) {
            Log::error("AttendancePipeline [{$category}]: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return [];
        }
    }

    // -------------------------------------------------------------------------
    // BioStar queries (mirror of AttendanceController, without group/person filter)
    // -------------------------------------------------------------------------

    public function fetchPunchRecords(string $category, string $startDate, string $endDate): array
    {
        $params = [$startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate];

        $groupFilter = $this->buildGroupFilter($category);
        if ($groupFilter === null) {
            return [];
        }

        $devIds = "546271448, 546271450, 546271459, 546271456,
                   546271451, 546271446, 546271441, 546271465,
                   546271439, 546271455, 546271457, 546271449,
                   544406591, 546211175, 544404098, 546211180,
                   544406595, 546210407, 544406713,
                   544406604, 546211165, 546211177, 546210421,
                   544406602, 544406099, 546211171,
                   546271436, 546271464";

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

    public function fetchEmployees(string $category): array
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
    public function buildGroupFilter(string $category): ?string
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

    public function detectPython(): ?string
    {
        $envCommand = env('PYTHON_COMMAND');
        if ($envCommand) {
            return $envCommand;
        }

        $candidates = ['py -3.11', 'py -3', 'py', 'python3', 'python'];

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
