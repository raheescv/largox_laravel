<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeploymentPipeline extends Model
{
    protected $fillable = ['site_id', 'name', 'description', 'steps', 'branch', 'is_default'];

    protected $casts = [
        'steps' => 'array',
        'is_default' => 'boolean',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
