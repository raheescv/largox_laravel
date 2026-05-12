<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DeploymentController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::resource('servers', ServerController::class)->except(['create', 'edit']);
    Route::post('servers/{server}/dispatch', [ServerController::class, 'dispatchAction'])
        ->name('servers.dispatch');

    Route::get('servers/{server}/sites', [SiteController::class, 'index'])->name('servers.sites.index');
    Route::resource('sites', SiteController::class)->except(['index', 'create', 'edit']);
    Route::post('sites/{site}/clone', [SiteController::class, 'clone'])->name('sites.clone');
    Route::post('sites/{site}/env',   [SiteController::class, 'writeEnv'])->name('sites.env');

    Route::get('sites/{site}/deployments',  [DeploymentController::class, 'index'])->name('deployments.index');
    Route::post('sites/{site}/deployments', [DeploymentController::class, 'store'])->name('deployments.store');
    Route::get('deployments/{deployment}',  [DeploymentController::class, 'show'])->name('deployments.show');

    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit.index');
});

require __DIR__.'/settings.php';
