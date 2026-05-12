<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { FileText, PlusCircle, RefreshCw } from 'lucide-vue-next';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import nginxRoutes from '@/routes/nginx';
import { dashboard } from '@/routes';

type NginxSite = {
    name: string;
    enabled: boolean;
    size: number;
    mod_time: string;
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Nginx Sites', href: nginxRoutes.index() },
        ],
    },
});

const props = defineProps<{
    server: { id: number; name: string; host: string };
    sites: NginxSite[];
    error: string | null;
}>();

const storeForm = useForm({
    name: '',
    contents: defaultConfig(),
    enable: true,
});
const storeAgentError = computed(
    () => (storeForm.errors as Record<string, string>).agent,
);

function defaultConfig(): string {
    return `server {
    listen 80;
    server_name example.com;

    root /var/www/example.com/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \\.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}`;
}

function submitStore() {
    storeForm.post(nginxRoutes.store.url());
}

const reloadForm = useForm({});
function reload() {
    reloadForm.post(nginxRoutes.reload.url());
}

const enableForm = useForm({});
const disableForm = useForm({});

function enable(name: string) {
    enableForm.post(nginxRoutes.enable.url({ name }));
}
function disable(name: string) {
    disableForm.post(nginxRoutes.disable.url({ name }));
}

const deleteForm = useForm({});
function destroy(name: string) {
    if (confirm(`Delete nginx site "${name}"?`)) {
        deleteForm.delete(nginxRoutes.destroy.url({ name }));
    }
}
</script>

<template>
    <Head title="Nginx Sites" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                title="Nginx Sites"
                :description="`${server.name} · ${server.host}`"
            />
            <Button
                variant="outline"
                size="sm"
                :disabled="reloadForm.processing"
                @click="reload"
            >
                <RefreshCw class="mr-2 size-4" /> Reload nginx
            </Button>
        </div>

        <div
            v-if="error"
            class="rounded-xl border border-red-300 bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40"
        >
            {{ error }}
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <!-- Add site -->
            <Card>
                <CardHeader>
                    <CardTitle>New Site</CardTitle>
                    <CardDescription
                        >Write a config to sites-available</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submitStore">
                        <div class="grid gap-2">
                            <Label for="name">Config filename</Label>
                            <Input
                                id="name"
                                v-model="storeForm.name"
                                placeholder="example.com"
                                required
                            />
                            <p
                                v-if="storeForm.errors.name"
                                class="text-sm text-destructive"
                            >
                                {{ storeForm.errors.name }}
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <Label for="contents">Config contents</Label>
                            <textarea
                                id="contents"
                                v-model="storeForm.contents"
                                rows="12"
                                class="w-full resize-y rounded-md border border-input bg-background px-3 py-2 font-mono text-xs"
                                required
                            />
                            <p
                                v-if="storeForm.errors.contents"
                                class="text-sm text-destructive"
                            >
                                {{ storeForm.errors.contents }}
                            </p>
                        </div>
                        <label class="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                v-model="storeForm.enable"
                                class="rounded border-input"
                            />
                            Enable and reload nginx
                        </label>
                        <p
                            v-if="storeAgentError"
                            class="text-sm text-destructive"
                        >
                            {{ storeAgentError }}
                        </p>
                        <Button
                            type="submit"
                            :disabled="storeForm.processing"
                            class="w-full"
                        >
                            <PlusCircle class="mr-2 size-4" /> Create
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <!-- Site list -->
            <div class="space-y-3">
                <div
                    v-if="sites.length === 0"
                    class="flex flex-col items-center justify-center rounded-xl border border-dashed py-12 text-center text-muted-foreground"
                >
                    <FileText class="mb-3 size-10 opacity-40" />
                    <p class="text-sm">No sites in sites-available yet.</p>
                </div>
                <Card v-for="site in sites" :key="site.name">
                    <CardContent
                        class="flex items-center justify-between px-4 py-3"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <FileText
                                class="size-4 shrink-0 text-muted-foreground"
                            />
                            <div class="min-w-0">
                                <a
                                    :href="
                                        nginxRoutes.show.url({
                                            name: site.name,
                                        })
                                    "
                                    class="block truncate text-sm font-medium hover:underline"
                                >
                                    {{ site.name }}
                                </a>
                                <p class="text-xs text-muted-foreground">
                                    {{ site.size }} bytes · {{ site.mod_time }}
                                </p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <Badge
                                :variant="
                                    site.enabled ? 'default' : 'secondary'
                                "
                            >
                                {{ site.enabled ? 'enabled' : 'disabled' }}
                            </Badge>
                            <Button
                                v-if="!site.enabled"
                                variant="outline"
                                size="sm"
                                :disabled="enableForm.processing"
                                @click="enable(site.name)"
                                >Enable</Button
                            >
                            <Button
                                v-else
                                variant="outline"
                                size="sm"
                                :disabled="disableForm.processing"
                                @click="disable(site.name)"
                                >Disable</Button
                            >
                            <Button
                                variant="destructive"
                                size="sm"
                                :disabled="deleteForm.processing"
                                @click="destroy(site.name)"
                                >Delete</Button
                            >
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
