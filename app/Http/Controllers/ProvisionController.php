<?php

namespace App\Http\Controllers;

use App\Jobs\RunProvision;
use App\Models\Deployment;
use App\Models\Server;
use App\Models\Site;
use App\Services\NginxConfigGenerator;
use App\Services\SupervisorConfigGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProvisionController extends Controller
{
    public function create(Request $request, NginxConfigGenerator $nginx, SupervisorConfigGenerator $supervisor): Response
    {
        $server = Server::query()->orderBy('id')->firstOrFail();

        $domain = (string) $request->query('domain', '');
        $path = $domain ? "/var/www/html/{$domain}" : '';

        return Inertia::render('Provision/Create', [
            'server' => $server,
            'defaults' => [
                'domain' => $domain,
                'path' => $path,
                'nginx_config' => $domain ? $nginx->laravel($domain, $path) : '',
                'supervisor_config' => $domain ? $supervisor->laravelQueue($domain, $path) : '',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
            'path' => ['required', 'string', 'regex:/^\/(home|var|srv|opt)\/[A-Za-z0-9._\/\-]+$/'],
            'repository' => ['nullable', 'string', 'max:500'],
            'branch' => ['nullable', 'string', 'max:100'],
            'nginx_config' => ['nullable', 'string'],
            'nginx_name' => ['nullable', 'string', 'max:100'],
            'supervisor_config' => ['nullable', 'string'],
            'supervisor_name' => ['nullable', 'string', 'max:100'],
            'cron_user' => ['nullable', 'string', 'max:64'],
            'cron_entries' => ['nullable', 'array'],
            'cron_entries.*' => ['string'],
            'env_contents' => ['nullable', 'string'],
            'composer' => ['boolean'],
            'npm_build' => ['boolean'],
            'artisan_cmds' => ['nullable', 'array'],
            'artisan_cmds.*' => ['string', 'regex:/^[a-z0-9:_\-]+$/'],
        ]);

        $server = Server::query()->orderBy('id')->firstOrFail();

        $site = Site::create([
            'server_id' => $server->id,
            'domain' => $data['domain'],
            'path' => $data['path'],
            'repository' => $data['repository'] ?? null,
            'branch' => $data['branch'] ?? 'main',
            'composer' => $data['composer'] ?? false,
            'npm_build' => $data['npm_build'] ?? false,
            'artisan_cmds' => $data['artisan_cmds'] ?? [],
        ]);

        $deployment = Deployment::create([
            'site_id' => $site->id,
            'user_id' => $request->user()->id,
            'status' => Deployment::STATUS_QUEUED,
            'branch' => $data['branch'] ?? 'main',
        ]);

        $payload = [
            'path' => $data['path'],
            'domain' => $data['domain'],
            'repo' => $data['repository'] ?? '',
            'branch' => $data['branch'] ?? 'main',
            'nginx_config' => $data['nginx_config'] ?? '',
            'nginx_name' => $data['nginx_name'] ?? $data['domain'],
            'supervisor_config' => $data['supervisor_config'] ?? '',
            'supervisor_name' => $data['supervisor_name'] ?? $data['domain'],
            'cron_user' => $data['cron_user'] ?? 'www-data',
            'cron_entries' => array_filter($data['cron_entries'] ?? []),
            'env_contents' => $data['env_contents'] ?? '',
            'composer' => (bool) ($data['composer'] ?? false),
            'npm_build' => (bool) ($data['npm_build'] ?? false),
            'artisan_cmds' => $data['artisan_cmds'] ?? [],
        ];

        RunProvision::dispatch($deployment->id, $payload);

        return redirect()->route('deployments.show', $deployment);
    }
}
