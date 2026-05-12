<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\AgentClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SupervisorController extends Controller
{
    public function __construct(private AgentClient $agent)
    {
    }

    private function server(): Server
    {
        return Server::query()->orderBy('id')->firstOrFail();
    }

    public function index(): Response
    {
        try {
            $data = $this->agent->execute($this->server(), 'supervisor_list')['output'] ?? [];
            $error = null;
        } catch (Throwable $e) {
            $data = [];
            $error = $e->getMessage();
        }

        return Inertia::render('Supervisor/Index', [
            'files'  => $data['files']  ?? [],
            'status' => $data['status'] ?? null,
            'error'  => $error,
        ]);
    }

    public function show(string $name): Response
    {
        try {
            $file = $this->agent->execute($this->server(), 'supervisor_read', ['name' => $name])['output'] ?? null;
            $error = null;
        } catch (Throwable $e) {
            $file = null;
            $error = $e->getMessage();
        }

        return Inertia::render('Supervisor/Show', [
            'name'  => $name,
            'file'  => $file,
            'error' => $error,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9._\-]+\.conf$/'],
            'contents' => ['required', 'string'],
        ]);

        try {
            $this->agent->execute($this->server(), 'supervisor_write', $data);

            return redirect()->route('supervisor.show', $data['name'])->with('status', 'config saved');
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
            $this->agent->execute($this->server(), 'supervisor_write', [
                'name'     => $name,
                'contents' => $data['contents'],
            ]);

            return back()->with('status', 'config updated');
        } catch (Throwable $e) {
            return back()->withErrors(['agent' => $e->getMessage()]);
        }
    }

    public function destroy(string $name): RedirectResponse
    {
        try {
            $this->agent->execute($this->server(), 'supervisor_delete', ['name' => $name]);

            return redirect()->route('supervisor.index')->with('status', "$name removed");
        } catch (Throwable $e) {
            return back()->withErrors(['agent' => $e->getMessage()]);
        }
    }

    public function control(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'program' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9._\-]+$/'],
            'action'  => ['required', 'in:start,stop,restart'],
        ]);

        $action = match ($data['action']) {
            'start'   => 'supervisor_start',
            'stop'    => 'supervisor_stop',
            'restart' => 'supervisor_restart',
        };

        try {
            $this->agent->execute($this->server(), $action, ['name' => $data['program'], 'program' => $data['program']]);

            return back()->with('status', "{$data['program']} {$data['action']}ed");
        } catch (Throwable $e) {
            return back()->withErrors(['agent' => $e->getMessage()]);
        }
    }
}
