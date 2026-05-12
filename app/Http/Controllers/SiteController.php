<?php

namespace App\Http\Controllers;

use App\Http\Requests\SiteRequest;
use App\Models\Server;
use App\Models\Site;
use App\Services\AgentClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SiteController extends Controller
{
    public function index(Server $server): Response
    {
        return Inertia::render('Sites/Index', [
            'server' => $server,
            'sites' => $server->sites()->latest()->get(),
        ]);
    }

    public function show(Site $site): Response
    {
        return Inertia::render('Sites/Show', [
            'site' => $site->load('server'),
            'deployments' => $site->deployments()->latest()->limit(20)->get(),
        ]);
    }

    public function store(SiteRequest $request): RedirectResponse
    {
        $site = Site::create($request->validated());

        return redirect()->route('sites.show', $site);
    }

    public function update(SiteRequest $request, Site $site): RedirectResponse
    {
        $site->update($request->validated());

        return back();
    }

    public function destroy(Site $site): RedirectResponse
    {
        $serverId = $site->server_id;
        $site->delete();

        return redirect()->route('servers.sites.index', $serverId);
    }

    public function clone(Site $site, AgentClient $agent, Request $request): RedirectResponse
    {
        $request->validate(['repository' => ['required', 'string'], 'branch' => ['nullable', 'string']]);

        try {
            $agent->execute($site->server, 'git_clone', [
                'path' => $site->path,
                'repo' => $request->input('repository'),
                'branch' => $request->input('branch', $site->branch),
            ]);

            return back()->with('status', 'repository cloned');
        } catch (Throwable $e) {
            return back()->withErrors(['agent' => $e->getMessage()]);
        }
    }

    public function writeEnv(Site $site, AgentClient $agent, Request $request): RedirectResponse
    {
        $request->validate(['contents' => ['required', 'string']]);

        try {
            $agent->execute($site->server, 'env_write', [
                'path' => $site->path,
                'contents' => $request->input('contents'),
            ]);

            return back()->with('status', '.env written');
        } catch (Throwable $e) {
            return back()->withErrors(['agent' => $e->getMessage()]);
        }
    }
}
