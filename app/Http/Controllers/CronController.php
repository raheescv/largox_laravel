<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\AgentClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CronController extends Controller
{
    public function __construct(private AgentClient $agent)
    {
    }

    private function server(): Server
    {
        return Server::query()->orderBy('id')->firstOrFail();
    }

    public function index(Request $request): Response
    {
        $user = $request->query('user', 'root');

        try {
            $out = $this->agent->execute($this->server(), 'cron_read', ['user' => $user])['output'] ?? null;
            $error = null;
        } catch (Throwable $e) {
            $out = null;
            $error = $e->getMessage();
        }

        return Inertia::render('Cron/Index', [
            'user'     => $user,
            'contents' => $out['contents'] ?? '',
            'error'    => $error,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user'     => ['nullable', 'string', 'max:32', 'regex:/^[a-z_][a-z0-9_-]{0,31}$/'],
            'contents' => ['required', 'string'],
        ]);

        try {
            $this->agent->execute($this->server(), 'cron_write', [
                'user'     => $data['user'] ?? 'root',
                'contents' => $data['contents'],
            ]);

            return back()->with('status', 'crontab saved');
        } catch (Throwable $e) {
            return back()->withErrors(['agent' => $e->getMessage()]);
        }
    }
}
