<?php

namespace App\Console\Commands;

use App\Services\AttendancePipeline;
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

    private AttendancePipeline $pipeline;

    public function __construct(AttendancePipeline $pipeline)
    {
        parent::__construct();
        $this->pipeline = $pipeline;
    }

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

        if (!$this->pipeline->detectPython()) {
            $this->error('Python not found. Set PYTHON_COMMAND in .env or install Python 3 and add it to PATH.');
            return 1;
        }

        $this->info("Pre-computing attendance: {$startDate} → {$endDate}");

        foreach (self::CATEGORIES as $category) {
            $this->info("  [{$category}] starting...");
            $processedData = $this->pipeline->computeForCategory($category, $startDate, $endDate);

            if (empty($processedData)) {
                $this->error("    [{$category}] no data produced — check storage/logs/laravel.log");
                continue;
            }

            $this->upsertCache($category, $processedData);
            $this->info("    [{$category}] saved " . count($processedData) . ' rows.');
        }

        $this->info('Pre-computation complete.');
        return 0;
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
}
