<?php

namespace App\Http\Controllers;

use App\Models\CommandPreset;
use App\Models\Deployment;
use App\Models\DeploymentPipeline;
use App\Models\Site;
use App\Services\AgentClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Throwable;

class DeploymentDashboardController extends Controller
{
    // ── Page ─────────────────────────────────────────────────────────────────

    public function show(Site $site): InertiaResponse
    {
        return Inertia::render('Deployments/Dashboard', [
            'site' => $site->load('server'),
            'deployments' => $site->deployments()->latest()->limit(30)->get(),
            'pipelines' => $site->pipelines()->orderBy('is_default', 'desc')->get(),
            'presets' => $site->commandPresets()->get(),
            'pipelineSteps' => $this->availableSteps(),
        ]);
    }

    // ── Quick Actions ─────────────────────────────────────────────────────────

    public function quickAction(Site $site, Request $request, AgentClient $agent): JsonResponse
    {
        $request->validate(['action' => ['required', 'string']]);

        $allowedQuickActions = [
            'git_pull',
            'composer_install',
            'composer_update',
            'artisan_migrate',
            'artisan_optimize',
            'restart_queue',
            'cache_clear',
            'config_cache',
            'route_cache',
            'view_cache',
        ];

        $action = $request->input('action');

        if (! in_array($action, $allowedQuickActions, true)) {
            return response()->json(['error' => 'Action not allowed'], 422);
        }

        try {
            $execId = $agent->streamStart(
                $site->server,
                $this->mapQuickActionToStreamAction($action),
                ['path' => $site->path]
            );

            return response()->json(['exec_id' => $execId]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Artisan Command ───────────────────────────────────────────────────────

    public function artisanCommand(Site $site, Request $request, AgentClient $agent): JsonResponse
    {
        $request->validate(['command' => ['required', 'string', 'regex:/^[a-z0-9:_\-]+$/']]);

        try {
            $execId = $agent->streamStart($site->server, 'artisan_command', [
                'path' => $site->path,
                'command' => $request->input('command'),
            ]);

            return response()->json(['exec_id' => $execId]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Custom Command ────────────────────────────────────────────────────────

    public function customCommand(Site $site, Request $request, AgentClient $agent): JsonResponse
    {
        $request->validate([
            'command' => ['required', 'string', 'in:composer,php,git,npm,yarn,node'],
            'args' => ['required', 'array'],
            'args.*' => ['string', 'max:255'],
        ]);

        try {
            $execId = $agent->streamStart($site->server, 'custom_command', [
                'path' => $site->path,
                'command' => $request->input('command'),
                'args' => $request->input('args'),
            ]);

            return response()->json(['exec_id' => $execId]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Pipeline Run ──────────────────────────────────────────────────────────

    public function runPipeline(Site $site, DeploymentPipeline $pipeline, AgentClient $agent): JsonResponse
    {
        abort_if($pipeline->site_id !== $site->id, 403);

        try {
            $execId = $agent->streamStart($site->server, 'deploy_pipeline', [
                'path' => $site->path,
                'branch' => $pipeline->branch ?? $site->branch,
                'steps' => $pipeline->steps,
            ]);

            // Record as a deployment entry
            Deployment::create([
                'site_id' => $site->id,
                'user_id' => auth()->id(),
                'status' => Deployment::STATUS_RUNNING,
                'branch' => $pipeline->branch ?? $site->branch,
                'started_at' => now(),
                'output' => json_encode(['exec_id' => $execId, 'pipeline' => $pipeline->name]),
            ]);

            return response()->json(['exec_id' => $execId]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Stream Proxy ──────────────────────────────────────────────────────────

    /**
     * SSE proxy: connect to Go agent's stream endpoint and relay to browser.
     */
    public function stream(Site $site, string $execId, AgentClient $agent): Response
    {
        abort_if(! preg_match('/^[a-f0-9]{32}$/', $execId), 400, 'Invalid exec id');

        $streamInfo = $agent->streamUrl($site->server, $execId);

        return response()->stream(function () use ($streamInfo) {
            $ch = curl_init($streamInfo['url']);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(
                fn ($k, $v) => "$k: $v",
                array_keys($streamInfo['headers']),
                array_values($streamInfo['headers'])
            ));
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 600);
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) {
                echo $data;
                ob_flush();
                flush();

                return strlen($data);
            });
            curl_exec($ch);
            curl_close($ch);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    // ── Stream Status ─────────────────────────────────────────────────────────

    public function streamStatus(Site $site, string $execId, AgentClient $agent): JsonResponse
    {
        abort_if(! preg_match('/^[a-f0-9]{32}$/', $execId), 400, 'Invalid exec id');

        try {
            $status = $agent->streamStatus($site->server, $execId);

            return response()->json($status);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Pipelines CRUD ────────────────────────────────────────────────────────

    public function storePipeline(Site $site, Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*' => ['string', 'in:'.implode(',', array_keys($this->availableSteps()))],
            'branch' => ['nullable', 'string', 'max:100'],
            'is_default' => ['boolean'],
        ]);

        if (! empty($data['is_default'])) {
            $site->pipelines()->update(['is_default' => false]);
        }

        $pipeline = $site->pipelines()->create($data);

        return response()->json($pipeline, 201);
    }

    public function updatePipeline(Site $site, DeploymentPipeline $pipeline, Request $request): JsonResponse
    {
        abort_if($pipeline->site_id !== $site->id, 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'steps' => ['sometimes', 'array', 'min:1'],
            'steps.*' => ['string', 'in:'.implode(',', array_keys($this->availableSteps()))],
            'branch' => ['nullable', 'string', 'max:100'],
            'is_default' => ['boolean'],
        ]);

        if (! empty($data['is_default'])) {
            $site->pipelines()->where('id', '!=', $pipeline->id)->update(['is_default' => false]);
        }

        $pipeline->update($data);

        return response()->json($pipeline);
    }

    public function destroyPipeline(Site $site, DeploymentPipeline $pipeline): JsonResponse
    {
        abort_if($pipeline->site_id !== $site->id, 403);
        $pipeline->delete();

        return response()->json(['deleted' => true]);
    }

    // ── Presets CRUD ──────────────────────────────────────────────────────────

    public function storePreset(Site $site, Request $request): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'command' => ['required', 'string', 'in:composer,php,git,npm,yarn,node'],
            'args' => ['required', 'array'],
            'args.*' => ['string', 'max:255'],
        ]);

        $preset = $site->commandPresets()->create($data);

        return response()->json($preset, 201);
    }

    public function destroyPreset(Site $site, CommandPreset $preset): JsonResponse
    {
        abort_if($preset->site_id !== $site->id, 403);
        $preset->delete();

        return response()->json(['deleted' => true]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function mapQuickActionToStreamAction(string $action): string
    {
        return match ($action) {
            'cache_clear' => 'artisan_command',
            'config_cache' => 'artisan_command',
            'route_cache' => 'artisan_command',
            'view_cache' => 'artisan_command',
            default => $action,
        };
    }

    private function availableSteps(): array
    {
        return [
            'git_pull' => 'Git Pull',
            'composer_install' => 'Composer Install',
            'composer_update' => 'Composer Update',
            'migrate' => 'Artisan Migrate',
            'optimize' => 'Artisan Optimize',
            'queue_restart' => 'Restart Queues',
            'cache_clear' => 'Cache Clear',
            'config_cache' => 'Config Cache',
            'route_cache' => 'Route Cache',
            'view_cache' => 'View Cache',
        ];
    }
}
