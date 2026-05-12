<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'host', 'port', 'scheme',
        'agent_secret', 'ssh_private_key', 'ssh_user', 'ssh_port',
        'status', 'last_seen_at', 'meta',
    ];

    protected $hidden = ['agent_secret', 'ssh_private_key'];

    protected $casts = [
        'agent_secret' => 'encrypted',
        'ssh_private_key' => 'encrypted',
        'meta' => 'array',
        'last_seen_at' => 'datetime',
    ];

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function baseUrl(): string
    {
        return sprintf('%s://%s:%d', $this->scheme, $this->host, $this->port);
    }
}
