<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Plus, Trash2, ChevronRight } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Separator } from '@/components/ui/separator';
import provisionRoutes from '@/routes/provision';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'New Site', href: provisionRoutes.create() },
        ],
    },
});

const props = defineProps<{
    server: { id: number; name: string };
    defaults: {
        domain: string;
        path: string;
        nginx_config: string;
        supervisor_config: string;
    };
}>();

const form = useForm({
    domain: props.defaults.domain,
    path: props.defaults.path,
    repository: '',
    branch: 'main',
    nginx_config: props.defaults.nginx_config,
    nginx_name: props.defaults.domain,
    supervisor_config: props.defaults.supervisor_config,
    supervisor_name: props.defaults.domain ? `${props.defaults.domain}.conf` : '',
    cron_user: 'www-data',
    cron_entries: [] as string[],
    env_contents: '',
    composer: true,
    npm_build: false,
    artisan_cmds: ['migrate --force', 'config:cache', 'route:cache', 'view:cache'],
    enable_supervisor: true,
    enable_nginx: true,
});

// Auto-fill path and configs when domain changes
watch(() => form.domain, (domain) => {
    if (domain && !form.path) {
        form.path = `/var/www/html/${domain}`;
    }
});

watch([() => form.domain, () => form.path], ([domain, path]) => {
    if (!domain || !path) return;
    if (form.nginx_config === props.defaults.nginx_config || !form.nginx_config) {
        form.nginx_config = generateNginxConfig(domain, path);
    }
    if (form.supervisor_config === props.defaults.supervisor_config || !form.supervisor_config) {
        form.supervisor_config = generateSupervisorConfig(domain, path);
    }
});

function generateNginxConfig(domain: string, path: string): string {
    return `server {
    listen 80;
    listen [::]:80;
    server_name ${domain};
    root ${path}/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \\.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\\.(?!well-known).* {
        deny all;
    }
}`;
}

function generateSupervisorConfig(domain: string, path: string): string {
    const program = domain.replace(/[\.\-]/g, '_');
    return `[program:${program}]
process_name=%(program_name)s_%(process_num)02d
command=php ${path}/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=${path}/storage/logs/worker.log
stopwaitsecs=3600`;
}

const artisanInput = computed({
    get: () => form.artisan_cmds.join('\n'),
    set: (v: string) => { form.artisan_cmds = v.split('\n').map(s => s.trim()).filter(Boolean); },
});

function addCronEntry() {
    form.cron_entries.push('');
}

function removeCronEntry(i: number) {
    form.cron_entries.splice(i, 1);
}

function submit() {
    form.post(provisionRoutes.store.url());
}
</script>

<template>
    <Head title="New Site" />

    <div class="space-y-6">
        <Heading title="Create New Site" description="Provision a complete site: directory, git clone, nginx, supervisor, cron, and deploy" />

        <form @submit.prevent="submit" class="space-y-6">

            <!-- Basic Info -->
            <Card>
                <CardHeader>
                    <CardTitle>Site Details</CardTitle>
                    <CardDescription>Domain and filesystem path for the new site</CardDescription>
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="domain">Domain</Label>
                        <Input id="domain" v-model="form.domain" placeholder="example.com" required />
                        <p v-if="form.errors.domain" class="text-sm text-destructive">{{ form.errors.domain }}</p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="path">Path</Label>
                        <Input id="path" v-model="form.path" placeholder="/var/www/html/example.com" required />
                        <p v-if="form.errors.path" class="text-sm text-destructive">{{ form.errors.path }}</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Git -->
            <Card>
                <CardHeader>
                    <CardTitle>Git Repository</CardTitle>
                    <CardDescription>Optional — leave blank to skip cloning</CardDescription>
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="repo">Repository URL</Label>
                        <Input id="repo" v-model="form.repository" placeholder="git@github.com:org/repo.git" />
                        <p v-if="form.errors.repository" class="text-sm text-destructive">{{ form.errors.repository }}</p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="branch">Branch</Label>
                        <Input id="branch" v-model="form.branch" placeholder="main" />
                        <p v-if="form.errors.branch" class="text-sm text-destructive">{{ form.errors.branch }}</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Build Options -->
            <Card>
                <CardHeader>
                    <CardTitle>Build Options</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex items-center gap-3">
                        <Checkbox id="composer" v-model:checked="form.composer" />
                        <Label for="composer">Run <code class="font-mono text-xs">composer install --no-dev</code></Label>
                    </div>
                    <div class="flex items-center gap-3">
                        <Checkbox id="npm_build" v-model:checked="form.npm_build" />
                        <Label for="npm_build">Run <code class="font-mono text-xs">npm ci && npm run build</code></Label>
                    </div>
                    <Separator />
                    <div class="grid gap-2">
                        <Label for="artisan">Artisan commands (one per line)</Label>
                        <textarea
                            id="artisan"
                            v-model="artisanInput"
                            rows="5"
                            class="w-full font-mono text-xs rounded-md border border-input bg-background px-3 py-2 resize-y"
                            placeholder="migrate --force&#10;config:cache&#10;route:cache"
                        />
                        <p v-if="form.errors.artisan_cmds" class="text-sm text-destructive">{{ form.errors.artisan_cmds }}</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Environment file -->
            <Card>
                <CardHeader>
                    <CardTitle>.env Contents</CardTitle>
                    <CardDescription>Written to {{ form.path }}/.env — leave blank to skip</CardDescription>
                </CardHeader>
                <CardContent>
                    <textarea
                        v-model="form.env_contents"
                        rows="8"
                        class="w-full font-mono text-xs rounded-md border border-input bg-background px-3 py-2 resize-y"
                        placeholder="APP_NAME=MyApp&#10;APP_ENV=production&#10;DB_HOST=127.0.0.1&#10;..."
                    />
                </CardContent>
            </Card>

            <!-- Nginx -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Nginx Config</CardTitle>
                            <CardDescription>Leave blank to skip nginx setup</CardDescription>
                        </div>
                        <div class="flex items-center gap-2">
                            <Checkbox id="enable_nginx" v-model:checked="form.enable_nginx" />
                            <Label for="enable_nginx">Enable nginx config</Label>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="grid gap-2">
                        <Label for="nginx_name">Config filename (default: domain)</Label>
                        <Input id="nginx_name" v-model="form.nginx_name" :placeholder="form.domain" class="max-w-xs" />
                    </div>
                    <textarea
                        v-model="form.nginx_config"
                        rows="22"
                        class="w-full font-mono text-xs rounded-md border border-input bg-background px-3 py-2 resize-y"
                    />
                    <p v-if="form.errors.nginx_config" class="text-sm text-destructive">{{ form.errors.nginx_config }}</p>
                </CardContent>
            </Card>

            <!-- Supervisor -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Supervisor Config</CardTitle>
                            <CardDescription>Leave blank to skip queue worker setup</CardDescription>
                        </div>
                        <div class="flex items-center gap-2">
                            <Checkbox id="enable_supervisor" v-model:checked="form.enable_supervisor" />
                            <Label for="enable_supervisor">Add supervisor config</Label>
                        </div>
                    </div>
                </CardHeader>
                <CardContent v-if="form.enable_supervisor" class="space-y-3">
                    <div class="grid gap-2">
                        <Label for="sup_name">Config filename (default: domain.conf)</Label>
                        <Input id="sup_name" v-model="form.supervisor_name" :placeholder="`${form.domain}.conf`" class="max-w-xs" />
                    </div>
                    <textarea
                        v-model="form.supervisor_config"
                        rows="14"
                        class="w-full font-mono text-xs rounded-md border border-input bg-background px-3 py-2 resize-y"
                    />
                    <p v-if="form.errors.supervisor_config" class="text-sm text-destructive">{{ form.errors.supervisor_config }}</p>
                </CardContent>
            </Card>

            <!-- Cron entries -->
            <Card>
                <CardHeader>
                    <CardTitle>Cron Entries</CardTitle>
                    <CardDescription>Added to crontab for the specified user</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="grid gap-2 max-w-xs">
                        <Label for="cron_user">Cron user</Label>
                        <Input id="cron_user" v-model="form.cron_user" placeholder="www-data" />
                    </div>
                    <div v-for="(entry, i) in form.cron_entries" :key="i" class="flex items-center gap-2">
                        <Input
                            v-model="form.cron_entries[i]"
                            class="font-mono text-xs"
                            placeholder="* * * * * php /var/www/html/example.com/artisan schedule:run >> /dev/null 2>&1"
                        />
                        <Button type="button" variant="ghost" size="icon" @click="removeCronEntry(i)">
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                    <Button type="button" variant="outline" size="sm" @click="addCronEntry">
                        <Plus class="mr-2 size-4" /> Add cron entry
                    </Button>
                </CardContent>
            </Card>

            <div class="flex justify-end gap-3">
                <p v-if="form.errors.path" class="text-sm text-destructive self-center">{{ form.errors.path }}</p>
                <Button type="submit" :disabled="form.processing" size="lg">
                    <ChevronRight class="mr-2 size-4" />
                    Provision Site
                </Button>
            </div>
        </form>
    </div>
</template>
