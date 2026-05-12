<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    Activity,
    FileCog,
    PauseCircle,
    PlayCircle,
    PlusCircle,
    RefreshCw,
    Search,
    SlidersHorizontal,
    Trash2,
    X,
} from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
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
import supervisorRoutes from '@/routes/supervisor';
import { dashboard } from '@/routes';

type SupervisorFile = { name: string; size: number };
type ExecutorResult = {
    stdout?: string;
    stderr?: string;
    exit_code?: number;
} | null;

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Supervisor', href: supervisorRoutes.index() },
        ],
    },
});

const props = defineProps<{
    files: SupervisorFile[];
    status: ExecutorResult;
    error: string | null;
}>();

const search = ref('');
const sizeFilter = ref<'all' | 'small' | 'large'>('all');

const storeForm = useForm({
    name: '',
    contents: defaultConfig(),
});

function defaultConfig(): string {
    return `[program:laravel-worker]
command=php /var/www/example.com/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/worker.log
stopwaitsecs=3600`;
}

function submitStore() {
    storeForm.post(supervisorRoutes.store.url());
}

const controlForm = useForm({
    program: '',
    action: 'restart' as 'start' | 'stop' | 'restart',
});
function control(program: string, action: 'start' | 'stop' | 'restart') {
    controlForm.program = program;
    controlForm.action = action;
    controlForm.post(supervisorRoutes.control.url());
}

const deleteForm = useForm({});
function destroy(name: string) {
    if (confirm(`Remove supervisor config "${name}"?`)) {
        deleteForm.delete(supervisorRoutes.destroy.url({ name }));
    }
}

const filteredFiles = computed(() => {
    const q = search.value.trim().toLowerCase();

    return props.files.filter((file) => {
        const matchesSearch = !q || file.name.toLowerCase().includes(q);
        const matchesSize =
            sizeFilter.value === 'all' ||
            (sizeFilter.value === 'small' && file.size < 4096) ||
            (sizeFilter.value === 'large' && file.size >= 4096);

        return matchesSearch && matchesSize;
    });
});

const totalBytes = computed(() =>
    props.files.reduce((sum, file) => sum + file.size, 0),
);
const storeAgentError = computed(
    () => (storeForm.errors as Record<string, string>).agent,
);

function programName(name: string) {
    return name.replace(/\.conf$/, '');
}

function formatBytes(bytes: number) {
    if (bytes < 1024) return `${bytes} B`;
    return `${(bytes / 1024).toFixed(1)} KB`;
}

function clearFilters() {
    search.value = '';
    sizeFilter.value = 'all';
}
</script>

<template>
    <Head title="Supervisor" />

    <div class="ops-page">
        <section class="ops-hero">
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
            >
                <Heading
                    title="Supervisor"
                    description="Manage process configs, workers, daemons, and restart actions from one control surface."
                    class="mb-0"
                />
                <div class="grid grid-cols-2 gap-2 sm:min-w-72">
                    <div class="ops-stat">
                        <p class="text-xs text-muted-foreground">Programs</p>
                        <p class="mt-1 text-2xl font-semibold">
                            {{ files.length }}
                        </p>
                    </div>
                    <div class="ops-stat">
                        <p class="text-xs text-muted-foreground">Config size</p>
                        <p class="mt-1 text-2xl font-semibold">
                            {{ formatBytes(totalBytes) }}
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

        <Card v-if="status">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Activity class="size-5 text-primary" />
                    supervisorctl status
                </CardTitle>
            </CardHeader>
            <CardContent>
                <pre
                    class="overflow-x-auto rounded-md bg-muted p-3 font-mono text-xs whitespace-pre-wrap"
                    >{{ status.stdout ?? status }}</pre
                >
            </CardContent>
        </Card>

        <div class="grid gap-6 xl:grid-cols-[0.9fr_1.35fr]">
            <Card class="h-fit">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <PlusCircle class="size-5 text-primary" />
                        New Program
                    </CardTitle>
                    <CardDescription
                        >Add a .conf file to
                        /etc/supervisor/conf.d.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submitStore">
                        <div class="grid gap-2">
                            <Label for="name"
                                >Filename (must end in .conf)</Label
                            >
                            <Input
                                id="name"
                                v-model="storeForm.name"
                                placeholder="laravel-worker.conf"
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
                            <Label for="contents">Config</Label>
                            <textarea
                                id="contents"
                                v-model="storeForm.contents"
                                rows="12"
                                class="ops-code-textarea"
                                required
                            />
                        </div>
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
                            <PlusCircle class="mr-2 size-4" /> Add Program
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
                            placeholder="Search program configs"
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
                                v-model="sizeFilter"
                                class="h-9 rounded-md border border-input bg-background pr-8 pl-9 text-sm shadow-sm"
                            >
                                <option value="all">All configs</option>
                                <option value="small">Under 4 KB</option>
                                <option value="large">4 KB and up</option>
                            </select>
                        </div>
                        <Button
                            v-if="search || sizeFilter !== 'all'"
                            variant="ghost"
                            size="sm"
                            @click="clearFilters"
                        >
                            <X class="mr-2 size-4" />
                            Reset
                        </Button>
                    </div>
                </div>

                <div
                    v-if="files.length === 0"
                    class="flex flex-col items-center justify-center rounded-lg border border-dashed bg-card/70 py-16 text-center text-muted-foreground"
                >
                    <FileCog class="mb-3 size-10 opacity-40" />
                    <p class="text-sm">No .conf files yet.</p>
                </div>
                <div
                    v-else-if="filteredFiles.length === 0"
                    class="flex flex-col items-center justify-center rounded-lg border border-dashed bg-card/70 py-16 text-center text-muted-foreground"
                >
                    <Search class="mb-3 size-10 opacity-40" />
                    <p class="text-sm">No programs match your filters.</p>
                    <Button variant="link" size="sm" @click="clearFilters"
                        >Clear filters</Button
                    >
                </div>
                <div v-else class="ops-list">
                    <div
                        v-for="f in filteredFiles"
                        :key="f.name"
                        class="ops-list-row"
                    >
                        <div class="flex min-w-0 items-start gap-3">
                            <div
                                class="mt-0.5 rounded-md border bg-background p-2 text-primary"
                            >
                                <FileCog class="size-5" />
                            </div>
                            <div class="min-w-0">
                                <a
                                    :href="
                                        supervisorRoutes.show.url({
                                            name: f.name,
                                        })
                                    "
                                    class="block truncate font-mono text-sm font-medium hover:underline"
                                >
                                    {{ f.name }}
                                </a>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ programName(f.name) }} ·
                                    {{ formatBytes(f.size) }}
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 sm:justify-end">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="controlForm.processing"
                                @click="control(programName(f.name), 'start')"
                            >
                                <PlayCircle class="mr-2 size-4" />
                                Start
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="controlForm.processing"
                                @click="control(programName(f.name), 'stop')"
                            >
                                <PauseCircle class="mr-2 size-4" />
                                Stop
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="controlForm.processing"
                                @click="control(programName(f.name), 'restart')"
                            >
                                <RefreshCw class="mr-2 size-4" />
                                Restart
                            </Button>
                            <Button
                                variant="destructive"
                                size="sm"
                                :disabled="deleteForm.processing"
                                @click="destroy(f.name)"
                            >
                                <Trash2 class="mr-2 size-4" />
                                Remove
                            </Button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
