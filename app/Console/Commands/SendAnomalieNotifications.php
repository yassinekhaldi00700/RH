<?php

namespace App\Console\Commands;

use App\Services\GraphMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sends the anomalie emails built by anomalie:build, for whichever rows are
 * still status=pending and not excluded. Respects the global on/off switch
 * in anomalie_mail_settings — when off, this does nothing at all.
 */
class SendAnomalieNotifications extends Command
{
    protected $signature = 'anomalie:send
        {--date= : YYYY-MM-DD override, default: the most recently built batch}
        {--only= : TEST MODE — redirect every email to this address instead of the real recipient. Does NOT change any row\'s status, so the real batch stays pending for a real run later.}';
    protected $description = 'Send pending anomalie notification emails (global switch permitting)';

    public function handle(GraphMailer $mailer): int
    {
        $settings = DB::table('anomalie_mail_settings')->first();

        if (!$settings || !$settings->enabled) {
            $this->info('Anomalie mail sending is disabled — nothing sent.');
            Log::info('anomalie:send skipped — sending disabled.');
            return 0;
        }

        $workDate = $this->option('date') ?: DB::table('anomalie_notifications')->max('work_date');

        if (!$workDate) {
            $this->info('No anomalie batch to send.');
            return 0;
        }

        $testRecipient = $this->option('only');

        $toSend = DB::table('anomalie_notifications')
            ->where('work_date', $workDate)
            ->where('status', 'pending')
            ->where('excluded', false)
            ->get();

        if ($testRecipient) {
            $this->warn("TEST MODE: {$toSend->count()} email(s) for {$workDate} will all be redirected to {$testRecipient}. No database status will change.");
        } else {
            $this->info("Sending {$toSend->count()} anomalie email(s) for {$workDate}...");
        }

        $sent = 0;
        $failed = 0;

        foreach ($toSend as $row) {
            $recipient = $testRecipient ?: $row->email;
            $subject = 'Anomalie de pointage — ' . $row->work_date;
            if ($testRecipient) {
                $subject = "[TEST — pour {$row->nom} <{$row->email}>] " . $subject;
            }

            try {
                $html = view('emails.anomalie', [
                    'nom'           => $row->nom,
                    'workDateLabel' => \Illuminate\Support\Carbon::parse($row->work_date)->translatedFormat('d/m/Y'),
                ])->render();

                $mailer->send($recipient, $subject, $html);

                if (!$testRecipient) {
                    DB::table('anomalie_notifications')->where('id', $row->id)->update([
                        'status'     => 'sent',
                        'sent_at'    => now(),
                        'error_message' => null,
                        'updated_at' => now(),
                    ]);
                }
                $sent++;
            } catch (\Throwable $e) {
                if (!$testRecipient) {
                    DB::table('anomalie_notifications')->where('id', $row->id)->update([
                        'status'        => 'failed',
                        'error_message' => $e->getMessage(),
                        'updated_at'    => now(),
                    ]);
                }
                Log::error("anomalie:send — failed for {$recipient} (real: {$row->email}): " . $e->getMessage());
                $failed++;
            }
        }

        $this->info("Done: {$sent} sent, {$failed} failed." . ($testRecipient ? ' (test mode — no status changed)' : ''));

        return 0;
    }
}
