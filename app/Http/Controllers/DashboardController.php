<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Server;
use App\Services\AgentClient;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DashboardController extends Controller
{
    public function __construct(private AgentClient $agent)
    {
    }

    public function __invoke(): Response
    {
        $server  = Server::query()->orderBy('id')->first();
        $metrics = null;
        $health  = null;
        $error   = null;

        if ($server) {
            try {
                $health  = $this->agent->health($server);
                $metrics = $this->agent->execute($server, 'system_metrics')['output'] ?? null;
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return Inertia::render('Dashboard', [
            'server'      => $server?->only(['id', 'name', 'host', 'status']),
            'health'      => $health,
            'metrics'     => $metrics,
            'error'       => $error,
            'recent_logs' => AuditLog::with('server')->latest()->limit(10)->get(),
        ]);
    }
}
