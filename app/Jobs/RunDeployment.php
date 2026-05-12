<?php

namespace App\Jobs;

use App\Models\Deployment;
use App\Services\AgentClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunDeployment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public int $deploymentId) {}

    public function handle(AgentClient $agent): void
    {
        /** @var Deployment $deployment */
        $deployment = Deployment::with('site.server')->findOrFail($this->deploymentId);
        $site = $deployment->site;

        $deployment->update([
            'status' => Deployment::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        try {
            $result = $agent->execute($site->server, 'deploy_project', [
                'path' => $site->path,
                'branch' => $deployment->branch ?? $site->branch,
                'composer' => (bool) $site->composer,
                'npm_build' => (bool) $site->npm_build,
                'artisan_cmds' => $site->artisan_cmds ?? [],
            ]);

            $deployment->update([
                'status' => Deployment::STATUS_SUCCESS,
                'output' => json_encode($result['output'] ?? null, JSON_PRETTY_PRINT),
                'finished_at' => now(),
            ]);

            if ($site->queue_program) {
                $agent->execute($site->server, 'supervisor_restart', ['name' => $site->queue_program]);
            }
        } catch (Throwable $e) {
            $deployment->update([
                'status' => Deployment::STATUS_FAILED,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);
            throw $e;
        }
    }
}
