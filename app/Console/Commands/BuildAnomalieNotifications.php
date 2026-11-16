<?php

namespace App\Console\Commands;

use App\Services\AttendancePipeline;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Finds personnel employees whose Status came out "anomalie" for the target
 * work day (default: the previous business day) and upserts them into
 * anomalie_notifications, ready for anomalie:send to mail out.
 *
 * Re-running for a date that was already built never resets an existing row's
 * `excluded`/`status` — it only adds rows that aren't tracked yet, so an
 * admin's manual review is never silently undone.
 */
class BuildAnomalieNotifications extends Command
{
    protected $signature = 'anomalie:build {--date= : YYYY-MM-DD override, default: previous business day}';
    protected $description = 'Build the list of personnel with an attendance anomalie for the target day';

    public function handle(AttendancePipeline $pipeline): int
    {
        $workDate = $this->option('date') ?: $this->previousBusinessDay(Carbon::today())->toDateString();

        $this->info("Building anomalie list for {$workDate}...");

        $processedData = $pipeline->computeForCategory('personnel', $workDate, $workDate);

        if (empty($processedData)) {
            $this->error('No data produced — check storage/logs/laravel.log');
            return 1;
        }

        $anomalies = array_values(array_filter(
            $processedData,
            fn($r) => ($r['Status'] ?? '') === 'anomalie'
        ));

        $this->info(count($anomalies) . ' anomalie row(s) found.');

        if (empty($anomalies)) {
            return 0;
        }

        $emailsByCode = DB::table('personnel_categorie')
            ->whereNotNull('email')
            ->pluck('email', 'id_biostar');

        $rows = [];
        $skipped = 0;
        $now = now();

        foreach ($anomalies as $record) {
            $code = (string) ($record['ID utilisateur'] ?? '');
            $email = $emailsByCode[$code] ?? null;

            if (!$code || !$email) {
                $skipped++;
                Log::warning("anomalie:build — no email for employee_code={$code} ({$record['Nom']}), skipped.");
                continue;
            }

            $rows[] = [
                'employee_code' => $code,
                'nom'           => $record['Nom'] ?? '',
                'email'         => $email,
                'work_date'     => $workDate,
                'excluded'      => false,
                'status'        => 'pending',
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        if (!empty($rows)) {
            // insertOrIgnore: the unique (employee_code, work_date) key makes this a
            // no-op for rows already tracked, so an admin's exclusion/status is safe.
            DB::table('anomalie_notifications')->insertOrIgnore($rows);
        }

        $this->info(count($rows) . " row(s) upserted, {$skipped} skipped (no email on file).");

        return 0;
    }

    private function previousBusinessDay(Carbon $date): Carbon
    {
        $d = $date->copy();
        do {
            $d->subDay();
        } while ($d->isWeekend());

        return $d;
    }
}
