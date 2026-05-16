<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommandPreset extends Model
{
    protected $fillable = ['site_id', 'label', 'command', 'args'];

    protected $casts = ['args' => 'array'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
