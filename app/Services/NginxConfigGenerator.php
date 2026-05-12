<?php

namespace App\Services;

class NginxConfigGenerator
{
    public function laravel(string $domain, string $path, string $phpFpm = 'php8.3-fpm'): string
    {
        $socket = "/run/php/{$phpFpm}.sock";

        return <<<NGINX
        server {
            listen 80;
            listen [::]:80;
            server_name {$domain};
            root {$path}/public;
            index index.php;

            add_header X-Frame-Options "SAMEORIGIN";
            add_header X-Content-Type-Options "nosniff";

            charset utf-8;

            location / {
                try_files \$uri \$uri/ /index.php?\$query_string;
            }

            location = /favicon.ico { access_log off; log_not_found off; }
            location = /robots.txt  { access_log off; log_not_found off; }

            error_page 404 /index.php;

            location ~ \\.php$ {
                fastcgi_pass unix:{$socket};
                fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
                include fastcgi_params;
            }

            location ~ /\\.(?!well-known).* {
                deny all;
            }
        }
        NGINX;
    }

    public function static(string $domain, string $path): string
    {
        return <<<NGINX
        server {
            listen 80;
            listen [::]:80;
            server_name {$domain};
            root {$path};
            index index.html index.htm;

            location / {
                try_files \$uri \$uri/ =404;
            }
        }
        NGINX;
    }
}
