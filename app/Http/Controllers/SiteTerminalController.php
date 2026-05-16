<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Inertia\Inertia;
use Inertia\Response;

class SiteTerminalController extends Controller
{
    public function show(Site $site): Response
    {
        return Inertia::render('Sites/Terminal', [
            'site' => $site->load('server'),
        ]);
    }
}
