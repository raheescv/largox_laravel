<?php

namespace App\Http\Controllers;

use App\Jobs\RunDeployment;
use App\Models\Deployment;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeploymentController extends Controller
{
    public function index(Site $site): Response
    {
        return Inertia::render('Deployments/Index', [
            'site'        => $site,
            'deployments' => $site->deployments()->latest()->paginate(25),
        ]);
    }

    public function show(Deployment $deployment): Response
    {
        return Inertia::render('Deployments/Show', [
            'deployment' => $deployment->load('site.server', 'user'),
        ]);
    }

    public function store(Site $site, Request $request): RedirectResponse
    {
        $deployment = Deployment::create([
            'site_id' => $site->id,
            'user_id' => $request->user()->id,
            'status'  => Deployment::STATUS_QUEUED,
            'branch'  => $request->input('branch', $site->branch),
        ]);

        RunDeployment::dispatch($deployment->id);

        return redirect()->route('deployments.show', $deployment);
    }
}
