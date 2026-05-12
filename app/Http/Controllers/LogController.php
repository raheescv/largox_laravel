<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\AgentClient;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class LogController extends Controller
{
    public function __invoke(AgentClient $agent): Response
    {
        $server = Server::query()->orderBy('id')->firstOrFail();
        $path = request()->query('path', '');
        $lines = (int) request()->query('lines', 200);

        $logLines = [];
        $error = null;

        if ($path) {
            try {
                $result = $agent->execute($server, 'read_log', [
                    'path' => $path,
                    'lines' => min($lines, 2000),
                ]);
                $logLines = $result['output']['lines'] ?? [];
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return Inertia::render('Logs/Show', [
            'path' => $path,
            'lines' => $logLines,
            'error' => $error,
        ]);
    }
}
