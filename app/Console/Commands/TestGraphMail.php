<?php

namespace App\Console\Commands;

use App\Services\GraphMailer;
use Illuminate\Console\Command;

/**
 * Sends a single, harmless test email via Microsoft Graph — no database
 * involved, doesn't touch anomalie_notifications or any production data.
 * Use this to confirm the Graph credentials/sender actually work before
 * trusting anomalie:send with real recipients.
 */
class TestGraphMail extends Command
{
    protected $signature = 'mail:test-graph {email : Address to send the test email to}';
    protected $description = 'Send a one-off test email via Microsoft Graph to confirm the connection works';

    public function handle(GraphMailer $mailer): int
    {
        $to = $this->argument('email');

        $this->info("Sending test email to {$to} via Microsoft Graph (sender: " . env('MS_GRAPH_SENDER') . ')...');

        try {
            $mailer->send(
                $to,
                'Test — Système de Gestion des Présences',
                '<p>Ceci est un email de test envoyé via Microsoft Graph depuis le système de Gestion des Présences.</p>'
                . '<p>Si vous recevez ce message, l\'envoi fonctionne correctement.</p>'
                . '<p style="color:#8C8C98;font-size:12px;">Envoyé le ' . now()->format('d/m/Y H:i:s') . '</p>'
            );
        } catch (\Throwable $e) {
            $this->error('FAILED: ' . $e->getMessage());
            return 1;
        }

        $this->info('Sent successfully. Check the inbox (and spam folder) for ' . $to);
        return 0;
    }
}
