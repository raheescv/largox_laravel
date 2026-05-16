<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id', 'domain', 'path', 'repository', 'branch',
        'composer', 'npm_build', 'artisan_cmds', 'php_service', 'queue_program',
    ];

    protected $casts = [
        'composer' => 'boolean',
        'npm_build' => 'boolean',
        'artisan_cmds' => 'array',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    public function pipelines(): HasMany
    {
        return $this->hasMany(DeploymentPipeline::class);
    }

    public function commandPresets(): HasMany
    {
        return $this->hasMany(CommandPreset::class);
    }
}
