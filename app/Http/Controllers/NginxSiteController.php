<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\AgentClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Filesystem-backed nginx site management. No DB — the source of truth is
 * /etc/nginx/sites-available on the managed server.
 */
class NginxSiteController extends Controller
{
    public function __construct(private AgentClient $agent)
    {
    }

    private function server(): Server
    {
        // Single-server deployment — first registered server is "this" server.
        return Server::query()->orderBy('id')->firstOrFail();
    }

    public function index(): Response
    {
        $server = $this->server();
        $sites  = [];
        $error  = null;

        try {
            $sites = $this->agent->execute($server, 'nginx_list_sites')['output'] ?? [];
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        return Inertia::render('Nginx/Index', [
            'server' => $server->only(['id', 'name', 'host']),
            'sites'  => $sites,
            'error'  => $error,
        ]);
    }

    public function show(string $name): Response
    {
        $server = $this->server();
        $site   = null;
        $error  = null;

        try {
            $site = $this->agent->execute($server, 'nginx_read_site', ['name' => $name])['output'] ?? null;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        return Inertia::render('Nginx/Show', [
            'server' => $server->only(['id', 'name', 'host']),
            'site'   => $site,
            'name'   => $name,
            'error'  => $error,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9._\-]+$/'],
            'contents' => ['required', 'string'],
            'enable'   => ['boolean'],
        ]);

        try {
            $this->agent->execute($this->server(), 'nginx_write_site', [
                'name'     => $data['name'],
                'contents' => $data['contents'],
            ]);
            if (! empty($data['enable'])) {
                $this->agent->execute($this->server(), 'nginx_enable_site', ['name' => $data['name']]);
            }

            return redirect()->route('nginx.show', $data['name'])->with('status', 'site saved');
        } catch (Throwable $e) {
            return back()->withErrors(['agent' => $e->getMessage()])->withInput();
        }
    }

    public function update(Request $request, string $name): RedirectResponse
    {
        $data = $request->validate([
            'contents' => ['required', 'string'],
        ]);

        try {
            $this->agent->execute($this->server(), 'nginx_write_site', [
                'name'     => $name,
                'contents' => $data['contents'],
            ]);
            // Validate config before reloading is handled by enable; for an
            // already-enabled site, reload here.
            $this->agent->execute($this->server(), 'nginx_reload');

            return back()->with('status', 'site updated and nginx reloaded');
        } catch (Throwable $e) {
            return back()->withErrors(['agent' => $e->getMessage()]);
        }
    }

    public function enable(string $name): RedirectResponse
    {
        try {
            $this->agent->execute($this->server(), 'nginx_enable_site', ['name' => $name]);

            return back()->with('status', "$name enabled");
        } catch (Throwable $e) {
            return back()->withErrors(['agent' => $e->getMessage()]);
        }
    }

    public function disable(string $name): RedirectResponse
    {
        try {
            $this->agent->execute($this->server(), 'nginx_disable_site', ['name' => $name]);

            return back()->with('status', "$name disabled");
        } catch (Throwable $e) {
            return back()->withErrors(['agent' => $e->getMessage()]);
        }
    }

    public function destroy(string $name): RedirectResponse
    {
        try {
            $this->agent->execute($this->server(), 'nginx_delete_site', ['name' => $name]);

            return redirect()->route('nginx.index')->with('status', "$name deleted");
        } catch (Throwable $e) {
            return back()->withErrors(['agent' => $e->getMessage()]);
        }
    }

    public function reload(): RedirectResponse
    {
        try {
            $this->agent->execute($this->server(), 'nginx_reload');

            return back()->with('status', 'nginx reloaded');
        } catch (Throwable $e) {
            return back()->withErrors(['agent' => $e->getMessage()]);
        }
    }
}
