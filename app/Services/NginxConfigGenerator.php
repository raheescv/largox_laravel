<?php

namespace App\Services;

class NginxConfigGenerator
{
    public function laravel(string $domain, string $path, string $phpFpm = 'php8.4-fpm'): string
    {
        $socket = "/run/php/{$phpFpm}.sock";
        $serverNames = "{$domain} www.{$domain} *.{$domain}";

        return <<<NGINX
server {
    listen 80;
    server_name {$serverNames};
    root {$path}/public;
    index index.php;

    client_max_body_size 50M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \\.php$ {
        try_files \$uri =404;
        fastcgi_pass unix:{$socket};
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\\.ht {
        deny all;
    }

    error_page 404 /404.html;
    error_page 500 502 503 504 /50x.html;
    location = /50x.html {
        root /usr/share/nginx/html;
    }

    listen 443 ssl;
    ssl_certificate /etc/nginx/ssl/certificate.crt;
    ssl_certificate_key /etc/nginx/ssl/server.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers 'TLS_AES_128_GCM_SHA256:TLS_AES_256_GCM_SHA384:TLS_CHACHA20_POLY1305_SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384';
    ssl_prefer_server_ciphers off;
    ssl_session_timeout 1d;
    ssl_session_cache shared:SSL:50m;

    location /storage/ {
        root {$path}/public/;
        try_files \$uri \$uri/ =404;
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
