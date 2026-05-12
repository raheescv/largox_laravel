<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServerRequest;
use App\Models\Server;
use App\Services\AgentClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ServerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Servers/Index', [
            'servers' => Server::latest()->get(),
        ]);
    }

    public function show(Server $server, AgentClient $agent): Response
    {
        $health = null;
        try {
            $health = $agent->health($server);
        } catch (Throwable $e) {
            $health = ['reachable' => false, 'error' => $e->getMessage()];
        }

        return Inertia::render('Servers/Show', [
            'server' => $server->load('sites'),
            'health' => $health,
        ]);
    }

    public function store(ServerRequest $request): RedirectResponse
    {
        $server = Server::create($request->validated());

        return redirect()->route('servers.show', $server);
    }

    public function update(ServerRequest $request, Server $server): RedirectResponse
    {
        $data = $request->validated();
        if (empty($data['agent_secret'])) {
            unset($data['agent_secret']);
        }
        $server->update($data);

        return back();
    }

    public function destroy(Server $server): RedirectResponse
    {
        $server->delete();

        return redirect()->route('servers.index');
    }

    public function dispatchAction(Request $request, Server $server, AgentClient $agent): RedirectResponse
    {
        $data = $request->validate([
            'action'  => ['required', 'string'],
            'payload' => ['nullable', 'array'],
        ]);

        try {
            $agent->execute($server, $data['action'], $data['payload'] ?? []);

            return back()->with('status', "{$data['action']} executed");
        } catch (Throwable $e) {
            return back()->withErrors(['agent' => $e->getMessage()]);
        }
    }
}
