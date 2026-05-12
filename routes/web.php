<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\DeploymentController;
use App\Http\Controllers\NginxSiteController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SupervisorController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome', [
    'canRegister' => false,
])->name('home');

// Hard-block registration in case the Fortify feature is ever re-enabled.
Route::match(['get', 'post'], '/register', fn () => abort(404));

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::resource('servers', ServerController::class)->except(['create', 'edit']);
    Route::post('servers/{server}/dispatch', [ServerController::class, 'dispatchAction'])
        ->name('servers.dispatch');

    Route::get('servers/{server}/sites', [SiteController::class, 'index'])->name('servers.sites.index');
    Route::resource('sites', SiteController::class)->except(['index', 'create', 'edit']);
    Route::post('sites/{site}/clone', [SiteController::class, 'clone'])->name('sites.clone');
    Route::post('sites/{site}/env', [SiteController::class, 'writeEnv'])->name('sites.env');

    Route::get('sites/{site}/deployments', [DeploymentController::class, 'index'])->name('deployments.index');
    Route::post('sites/{site}/deployments', [DeploymentController::class, 'store'])->name('deployments.store');
    Route::get('deployments/{deployment}', [DeploymentController::class, 'show'])->name('deployments.show');

    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit.index');

    // Nginx site files (filesystem-backed, single server)
    Route::get('nginx',               [NginxSiteController::class, 'index'])->name('nginx.index');
    Route::post('nginx',              [NginxSiteController::class, 'store'])->name('nginx.store');
    Route::post('nginx/reload',       [NginxSiteController::class, 'reload'])->name('nginx.reload');
    Route::get('nginx/{name}',        [NginxSiteController::class, 'show'])->name('nginx.show')->where('name', '[A-Za-z0-9._\-]+');
    Route::put('nginx/{name}',        [NginxSiteController::class, 'update'])->name('nginx.update')->where('name', '[A-Za-z0-9._\-]+');
    Route::delete('nginx/{name}',     [NginxSiteController::class, 'destroy'])->name('nginx.destroy')->where('name', '[A-Za-z0-9._\-]+');
    Route::post('nginx/{name}/enable',  [NginxSiteController::class, 'enable'])->name('nginx.enable')->where('name', '[A-Za-z0-9._\-]+');
    Route::post('nginx/{name}/disable', [NginxSiteController::class, 'disable'])->name('nginx.disable')->where('name', '[A-Za-z0-9._\-]+');

    // Supervisor configs + control
    Route::get('supervisor',           [SupervisorController::class, 'index'])->name('supervisor.index');
    Route::post('supervisor',          [SupervisorController::class, 'store'])->name('supervisor.store');
    Route::post('supervisor/control',  [SupervisorController::class, 'control'])->name('supervisor.control');
    Route::get('supervisor/{name}',    [SupervisorController::class, 'show'])->name('supervisor.show')->where('name', '[A-Za-z0-9._\-]+\.conf');
    Route::put('supervisor/{name}',    [SupervisorController::class, 'update'])->name('supervisor.update')->where('name', '[A-Za-z0-9._\-]+\.conf');
    Route::delete('supervisor/{name}', [SupervisorController::class, 'destroy'])->name('supervisor.destroy')->where('name', '[A-Za-z0-9._\-]+\.conf');

    // Crontab
    Route::get('cron',  [CronController::class, 'index'])->name('cron.index');
    Route::put('cron',  [CronController::class, 'update'])->name('cron.update');
});

require __DIR__.'/settings.php';
