<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Server;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AgentClient
{
    /**
     * Send a signed command to a server's Go agent. Records an audit log
     * regardless of outcome and returns the decoded JSON body.
     *
     * @return array{status: string, output?: mixed, error?: string}
     */
    public function execute(Server $server, string $action, array $payload = []): array
    {
        $path = '/v1/execute';
        $body = json_encode([
            'server_id' => (string) $server->id,
            'action'    => $action,
            'payload'   => (object) $payload,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $ts  = (string) time();
        $sig = hash_hmac('sha256', "$ts\nPOST\n$path\n$body", $server->agent_secret);

        try {
            $response = Http::withHeaders([
                'X-Agent-Timestamp' => $ts,
                'X-Agent-Signature' => $sig,
                'Content-Type'      => 'application/json',
                'Accept'            => 'application/json',
            ])
                ->connectTimeout(config('agent.connect_timeout'))
                ->timeout(config('agent.request_timeout'))
                ->withBody($body, 'application/json')
                ->post($server->baseUrl() . $path);
        } catch (ConnectionException $e) {
            $this->audit($server, $action, $payload, 'fail', $e->getMessage());
            throw new RuntimeException("agent unreachable: {$e->getMessage()}", 0, $e);
        }

        $this->recordAndCheck($response, $server, $action, $payload);

        return $response->json() ?? [];
    }

    public function health(Server $server): array
    {
        $response = Http::connectTimeout(3)->timeout(5)->get($server->baseUrl() . '/healthz');

        return [
            'reachable' => $response->successful(),
            'body'      => $response->json(),
        ];
    }

    private function recordAndCheck(Response $response, Server $server, string $action, array $payload): void
    {
        $ok = $response->successful() && ($response->json('status') === 'success');

        $this->audit(
            $server,
            $action,
            $payload,
            $ok ? 'ok' : 'fail',
            (string) $response->body(),
        );

        if (! $ok) {
            $err = $response->json('error') ?? ('HTTP ' . $response->status());
            throw new RuntimeException("agent error: $err");
        }
    }

    private function audit(Server $server, string $action, array $payload, string $status, ?string $response): void
    {
        AuditLog::create([
            'user_id'   => Auth::id(),
            'server_id' => $server->id,
            'action'    => $action,
            'payload'   => $payload,
            'status'    => $status,
            'response'  => $response,
            'ip'        => request()?->ip(),
        ]);
    }
}
