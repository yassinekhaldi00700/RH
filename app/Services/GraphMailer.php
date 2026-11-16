<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Sends mail through Microsoft Graph (client-credentials app, sending as
 * MS_GRAPH_SENDER) instead of SMTP. Config: MS_GRAPH_TENANT_ID,
 * MS_GRAPH_CLIENT_ID, MS_GRAPH_CLIENT_SECRET, MS_GRAPH_SENDER in .env.
 */
class GraphMailer
{
    private const CACHE_KEY = 'ms_graph_access_token';

    /**
     * @throws \RuntimeException on any failure (token or send) — caller decides
     *         how to record it (e.g. mark a notification row as failed).
     */
    public function send(string $toEmail, string $subject, string $htmlBody): void
    {
        $token  = $this->getAccessToken();
        $sender = env('MS_GRAPH_SENDER');

        if (!$sender) {
            throw new \RuntimeException('MS_GRAPH_SENDER is not configured.');
        }

        $response = Http::withToken($token)
            ->post("https://graph.microsoft.com/v1.0/users/{$sender}/sendMail", [
                'message' => [
                    'subject' => $subject,
                    'body' => [
                        'contentType' => 'HTML',
                        'content'     => $htmlBody,
                    ],
                    'toRecipients' => [
                        ['emailAddress' => ['address' => $toEmail]],
                    ],
                ],
                'saveToSentItems' => true,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                "Graph sendMail failed ({$response->status()}): " . $response->body()
            );
        }
    }

    private function getAccessToken(): string
    {
        return Cache::remember(self::CACHE_KEY, 3300, function () { // just under the usual 1h expiry
            $tenantId = env('MS_GRAPH_TENANT_ID');
            $clientId = env('MS_GRAPH_CLIENT_ID');
            $secret   = env('MS_GRAPH_CLIENT_SECRET');

            if (!$tenantId || !$clientId || !$secret) {
                throw new \RuntimeException('MS_GRAPH_TENANT_ID / MS_GRAPH_CLIENT_ID / MS_GRAPH_CLIENT_SECRET missing in .env.');
            }

            $response = Http::asForm()->post(
                "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
                [
                    'client_id'     => $clientId,
                    'client_secret' => $secret,
                    'scope'         => 'https://graph.microsoft.com/.default',
                    'grant_type'    => 'client_credentials',
                ]
            );

            if ($response->failed() || !$response->json('access_token')) {
                throw new \RuntimeException('Failed to obtain Graph access token: ' . $response->body());
            }

            return $response->json('access_token');
        });
    }
}
