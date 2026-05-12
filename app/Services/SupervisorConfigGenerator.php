<?php

namespace App\Services;

class SupervisorConfigGenerator
{
    public function laravelQueue(string $domain, string $path, string $user = 'www-data', int $numprocs = 1): string
    {
        $program = str_replace(['.', '-'], '_', $domain);

        return <<<INI
        [program:{$program}]
        process_name=%(program_name)s_%(process_num)02d
        command=php {$path}/artisan queue:work --sleep=3 --tries=3 --max-time=3600
        autostart=true
        autorestart=true
        stopasgroup=true
        killasgroup=true
        user={$user}
        numprocs={$numprocs}
        redirect_stderr=true
        stdout_logfile={$path}/storage/logs/worker.log
        stopwaitsecs=3600
        INI;
    }
}
