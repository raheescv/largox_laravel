<?php

namespace App\Services;

class SupervisorConfigGenerator
{
    public function laravelQueue(string $domain, string $path, string $user = 'root', int $numprocs = 1): string
    {
        $program = preg_replace('/[^A-Za-z0-9]+/', '', explode('.', $domain)[0]) ?: 'laravel';

        return <<<INI
[program:{$program}-default-queue]
process_name = %(program_name)s_%(process_num)02d
command=php {$path}/artisan queue:work --queue=default --daemon
autostart=true
autorestart=true
startsecs=0
user={$user}
numprocs={$numprocs}
redirect_stderr=true
stdout_logfile={$path}/storage/logs/worker.log

[program:{$program}-visitors-queue]
process_name = %(program_name)s_%(process_num)02d
command=php {$path}/artisan queue:work --queue=visitors --daemon
autostart=true
autorestart=true
startsecs=0
user={$user}
numprocs={$numprocs}
redirect_stderr=true
stdout_logfile={$path}/storage/logs/worker.log
INI;
    }
}
