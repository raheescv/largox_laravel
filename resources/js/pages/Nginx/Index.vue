<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    FileText,
    Globe,
    PlusCircle,
    Power,
    PowerOff,
    RefreshCw,
    Search,
    SlidersHorizontal,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
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
const search = ref('');
const statusFilter = ref<'all' | 'enabled' | 'disabled'>('all');

const storeForm = useForm({
    name: '',
    contents: defaultConfig(),
    enable: true,
});
const storeAgentError = computed(
    () => (storeForm.errors as Record<string, string>).agent,
);
const enabledSites = computed(
    () => props.sites.filter((site) => site.enabled).length,
);
const disabledSites = computed(() => props.sites.length - enabledSites.value);
const filteredSites = computed(() => {
    const q = search.value.trim().toLowerCase();

    return props.sites.filter((site) => {
        const matchesSearch =
            !q ||
            [site.name, site.mod_time, `${site.size}`]
                .join(' ')
                .toLowerCase()
                .includes(q);
        const matchesStatus =
            statusFilter.value === 'all' ||
            (statusFilter.value === 'enabled' && site.enabled) ||
            (statusFilter.value === 'disabled' && !site.enabled);

        return matchesSearch && matchesStatus;
    });
});

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

function formatBytes(bytes: number) {
    if (bytes < 1024) return `${bytes} B`;
    return `${(bytes / 1024).toFixed(1)} KB`;
}

function clearFilters() {
    search.value = '';
    statusFilter.value = 'all';
}
</script>

<template>
    <Head title="Nginx Sites" />

    <div class="ops-page">
        <section class="ops-hero">
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
            >
                <Heading
                    title="Nginx Sites"
                    :description="`${server.name} · ${server.host}`"
                    class="mb-0"
                />
                <div class="grid grid-cols-3 gap-2 sm:min-w-96">
                    <div class="ops-stat">
                        <p class="text-xs text-muted-foreground">Total</p>
                        <p class="mt-1 text-2xl font-semibold">
                            {{ sites.length }}
                        </p>
                    </div>
                    <div class="ops-stat">
                        <p class="text-xs text-muted-foreground">Enabled</p>
                        <p class="mt-1 text-2xl font-semibold">
                            {{ enabledSites }}
                        </p>
                    </div>
                    <div class="ops-stat">
                        <p class="text-xs text-muted-foreground">Disabled</p>
                        <p class="mt-1 text-2xl font-semibold">
                            {{ disabledSites }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <div
            v-if="error"
            class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40"
        >
            {{ error }}
        </div>

        <div class="grid gap-6 xl:grid-cols-[0.9fr_1.35fr]">
            <Card class="h-fit">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <PlusCircle class="size-5 text-primary" />
                        New Site
                    </CardTitle>
                    <CardDescription
                        >Write a config to sites-available.</CardDescription
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
                                class="ops-code-textarea"
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

            <section class="space-y-3">
                <div class="ops-toolbar">
                    <div class="ops-search">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="search"
                            class="pl-9"
                            placeholder="Search config name, size, or modified time"
                        />
                    </div>
                    <div
                        class="flex flex-col gap-2 sm:flex-row sm:items-center"
                    >
                        <div class="relative">
                            <SlidersHorizontal
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <select
                                v-model="statusFilter"
                                class="h-9 rounded-md border border-input bg-background pr-8 pl-9 text-sm shadow-sm"
                            >
                                <option value="all">All sites</option>
                                <option value="enabled">Enabled</option>
                                <option value="disabled">Disabled</option>
                            </select>
                        </div>
                        <Button
                            v-if="search || statusFilter !== 'all'"
                            variant="ghost"
                            size="sm"
                            @click="clearFilters"
                        >
                            <X class="mr-2 size-4" />
                            Reset
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="reloadForm.processing"
                            @click="reload"
                        >
                            <RefreshCw class="mr-2 size-4" /> Reload
                        </Button>
                    </div>
                </div>

                <div
                    v-if="sites.length === 0"
                    class="flex flex-col items-center justify-center rounded-lg border border-dashed bg-card/70 py-16 text-center text-muted-foreground"
                >
                    <Globe class="mb-3 size-10 opacity-40" />
                    <p class="text-sm">No sites in sites-available yet.</p>
                </div>
                <div
                    v-else-if="filteredSites.length === 0"
                    class="flex flex-col items-center justify-center rounded-lg border border-dashed bg-card/70 py-16 text-center text-muted-foreground"
                >
                    <Search class="mb-3 size-10 opacity-40" />
                    <p class="text-sm">No nginx sites match your filters.</p>
                    <Button variant="link" size="sm" @click="clearFilters">
                        Clear filters
                    </Button>
                </div>
                <div v-else class="ops-list">
                    <div
                        v-for="site in filteredSites"
                        :key="site.name"
                        class="ops-list-row"
                    >
                        <div class="flex min-w-0 items-start gap-3">
                            <div
                                class="mt-0.5 rounded-md border bg-background p-2 text-primary"
                            >
                                <FileText class="size-5" />
                            </div>
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
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ formatBytes(site.size) }} ·
                                    {{ site.mod_time }}
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 sm:justify-end">
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
                            >
                                <Power class="mr-2 size-4" />
                                Enable
                            </Button>
                            <Button
                                v-else
                                variant="outline"
                                size="sm"
                                :disabled="disableForm.processing"
                                @click="disable(site.name)"
                            >
                                <PowerOff class="mr-2 size-4" />
                                Disable
                            </Button>
                            <Button
                                variant="destructive"
                                size="sm"
                                :disabled="deleteForm.processing"
                                @click="destroy(site.name)"
                            >
                                <Trash2 class="mr-2 size-4" />
                                Delete
                            </Button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
