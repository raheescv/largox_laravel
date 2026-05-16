<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Activity,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    Circle,
    Clock,
    Code2,
    GitBranch,
    GitPullRequest,
    Layers,
    PackageCheck,
    Play,
    Plus,
    RefreshCw,
    RotateCcw,
    Server,
    Settings,
    Terminal,
    Trash2,
    XCircle,
    Zap,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
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
import { Separator } from '@/components/ui/separator';
import deploymentRoutes from '@/routes/deployments';
import serverRoutes from '@/routes/servers';
import serverSiteRoutes from '@/routes/servers/sites';
import siteRoutes from '@/routes/sites';
import { dashboard } from '@/routes';

// ── Types ─────────────────────────────────────────────────────────────────────

type ServerModel = { id: number; name: string };
type SiteModel = {
    id: number;
    domain: string;
    path: string;
    branch: string;
    server: ServerModel;
};
type DeploymentModel = {
    id: number;
    status: string;
    branch: string | null;
    created_at: string;
    started_at: string | null;
    finished_at: string | null;
    output: string | null;
};
type Pipeline = {
    id: number;
    name: string;
    description: string | null;
    steps: string[];
    branch: string | null;
    is_default: boolean;
};
type Preset = {
    id: number;
    label: string;
    command: string;
    args: string[];
};
type LogLine = {
    stream: 'stdout' | 'stderr' | 'meta';
    text: string;
    ts: string;
};

// ── Props ─────────────────────────────────────────────────────────────────────

defineOptions({
    layout: (pageProps: { site: SiteModel }) => ({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Servers', href: serverRoutes.index() },
            { title: pageProps.site.server.name, href: serverRoutes.show(pageProps.site.server) },
            { title: 'Sites', href: serverSiteRoutes.index(pageProps.site.server) },
            { title: pageProps.site.domain, href: siteRoutes.show(pageProps.site) },
            { title: 'Deploy', href: '#' },
        ],
    }),
});

const props = defineProps<{
    site: SiteModel;
    deployments: DeploymentModel[];
    pipelines: Pipeline[];
    presets: Preset[];
    pipelineSteps: Record<string, string>;
}>();

// ── Terminal state ────────────────────────────────────────────────────────────

const terminalLines = ref<LogLine[]>([]);
const terminalEl = ref<HTMLElement | null>(null);
const activeExecId = ref<string | null>(null);
const isRunning = ref(false);
const runStatus = ref<'idle' | 'running' | 'success' | 'error'>('idle');
const activeAction = ref('');
let evtSource: EventSource | null = null;
let streamDone = false;

function clearTerminal() {
    terminalLines.value = [];
    runStatus.value = 'idle';
}

function scrollTerminal() {
    nextTick(() => {
        if (terminalEl.value) {
            terminalEl.value.scrollTop = terminalEl.value.scrollHeight;
        }
    });
}

function connectStream(execId: string) {
    if (evtSource) { evtSource.close(); evtSource = null; }
    streamDone = false;
    activeExecId.value = execId;
    isRunning.value = true;
    runStatus.value = 'running';

    evtSource = new EventSource(`/sites/${props.site.id}/deploy/stream/${execId}`);

    evtSource.addEventListener('log', (e: MessageEvent) => {
        try {
            const line = JSON.parse(e.data) as LogLine;
            terminalLines.value.push(line);
            scrollTerminal();
        } catch {}
    });

    evtSource.addEventListener('done', () => {
        streamDone = true;
        evtSource?.close();
        evtSource = null;
        isRunning.value = false;
        const lastMeta = [...terminalLines.value].reverse().find((l) => l.stream === 'meta');
        runStatus.value = lastMeta?.text.startsWith('✗') ? 'error' : 'success';
        router.reload({ only: ['deployments'] });
    });

    evtSource.addEventListener('error', (e: MessageEvent) => {
        if (streamDone) return;
        streamDone = true;
        evtSource?.close();
        evtSource = null;
        isRunning.value = false;
        runStatus.value = 'error';
        try {
            const data = JSON.parse(e.data ?? '{}');
            terminalLines.value.push({ stream: 'stderr', text: `Agent error: ${data.error ?? 'unknown'}`, ts: new Date().toISOString() });
        } catch {}
    });

    evtSource.onerror = () => {
        if (streamDone) return;
        streamDone = true;
        evtSource?.close();
        evtSource = null;
        isRunning.value = false;
        runStatus.value = 'error';
        terminalLines.value.push({
            stream: 'stderr',
            text: '✗ Stream connection failed. Check that the Go agent is running and reachable.',
            ts: new Date().toISOString(),
        });
    };
}

onBeforeUnmount(() => {
    evtSource?.close();
});

// ── Quick Actions ─────────────────────────────────────────────────────────────

const quickActions = [
    { key: 'git_pull', label: 'Git Pull', icon: GitPullRequest, description: 'Pull latest changes' },
    { key: 'composer_install', label: 'Composer Install', icon: PackageCheck, description: 'Install PHP dependencies' },
    { key: 'composer_update', label: 'Composer Update', icon: RefreshCw, description: 'Update PHP dependencies' },
    { key: 'artisan_migrate', label: 'DB Migrate', icon: Activity, description: 'Run pending migrations' },
    { key: 'artisan_optimize', label: 'Optimize', icon: Zap, description: 'Cache config, routes, views' },
    { key: 'restart_queue', label: 'Restart Queues', icon: RotateCcw, description: 'Signal queue workers to restart' },
] as const;

async function runQuickAction(action: string, label: string) {
    clearTerminal();
    activeAction.value = label;
    terminalLines.value.push({
        stream: 'meta',
        text: `▶ ${label}`,
        ts: new Date().toISOString(),
    });

    const res = await fetch(`/sites/${props.site.id}/deploy/quick`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': getCsrf(),
            Accept: 'application/json',
        },
        body: JSON.stringify({ action }),
    });

    const json = await res.json();
    if (!res.ok || json.error || !json.exec_id) {
        terminalLines.value.push({
            stream: 'stderr',
            text: json.error ?? (!json.exec_id ? 'No exec_id — is the Go agent running?' : 'Request failed'),
            ts: new Date().toISOString(),
        });
        runStatus.value = 'error';
        isRunning.value = false;
        return;
    }
    connectStream(json.exec_id);
}

// ── Artisan Command ───────────────────────────────────────────────────────────

const artisanCmd = ref('');

async function runArtisan() {
    if (!artisanCmd.value.trim()) return;
    clearTerminal();
    activeAction.value = `php artisan ${artisanCmd.value}`;
    terminalLines.value.push({ stream: 'meta', text: `▶ php artisan ${artisanCmd.value}`, ts: new Date().toISOString() });

    const res = await fetch(`/sites/${props.site.id}/deploy/artisan`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getCsrf(), Accept: 'application/json' },
        body: JSON.stringify({ command: artisanCmd.value }),
    });
    const json = await res.json();
    if (!res.ok || json.error) {
        terminalLines.value.push({ stream: 'stderr', text: json.error ?? 'Request failed', ts: new Date().toISOString() });
        runStatus.value = 'error';
        return;
    }
    connectStream(json.exec_id);
}

// ── Custom Command ────────────────────────────────────────────────────────────

const customBin = ref('php');
const customArgs = ref('');
const customBins = ['composer', 'php', 'git', 'npm', 'yarn', 'node'];

async function runCustom() {
    if (!customArgs.value.trim()) return;
    clearTerminal();
    const args = customArgs.value.trim().split(/\s+/);
    activeAction.value = `${customBin.value} ${customArgs.value}`;
    terminalLines.value.push({ stream: 'meta', text: `▶ ${customBin.value} ${customArgs.value}`, ts: new Date().toISOString() });

    const res = await fetch(`/sites/${props.site.id}/deploy/custom`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getCsrf(), Accept: 'application/json' },
        body: JSON.stringify({ command: customBin.value, args }),
    });
    const json = await res.json();
    if (!res.ok || json.error) {
        terminalLines.value.push({ stream: 'stderr', text: json.error ?? 'Request failed', ts: new Date().toISOString() });
        runStatus.value = 'error';
        return;
    }
    connectStream(json.exec_id);
}

// ── Pipelines ─────────────────────────────────────────────────────────────────

const showPipelineForm = ref(false);
const newPipeline = ref({ name: '', description: '', steps: [] as string[], branch: '', is_default: false });
const pipelineError = ref('');

const availableStepKeys = computed(() => Object.keys(props.pipelineSteps));

function toggleStep(step: string) {
    const idx = newPipeline.value.steps.indexOf(step);
    if (idx === -1) newPipeline.value.steps.push(step);
    else newPipeline.value.steps.splice(idx, 1);
}

async function savePipeline() {
    pipelineError.value = '';
    if (!newPipeline.value.name || newPipeline.value.steps.length === 0) {
        pipelineError.value = 'Name and at least one step are required.';
        return;
    }
    const res = await fetch(`/sites/${props.site.id}/pipelines`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getCsrf(), Accept: 'application/json' },
        body: JSON.stringify(newPipeline.value),
    });
    if (res.ok) {
        showPipelineForm.value = false;
        newPipeline.value = { name: '', description: '', steps: [], branch: '', is_default: false };
        router.reload({ only: ['pipelines'] });
    } else {
        const j = await res.json();
        pipelineError.value = j.message ?? 'Failed to save pipeline.';
    }
}

async function deletePipeline(id: number) {
    if (!confirm('Delete this pipeline?')) return;
    await fetch(`/sites/${props.site.id}/pipelines/${id}`, {
        method: 'DELETE',
        headers: { 'X-XSRF-TOKEN': getCsrf() },
    });
    router.reload({ only: ['pipelines'] });
}

async function runPipeline(pipeline: Pipeline) {
    clearTerminal();
    activeAction.value = pipeline.name;
    terminalLines.value.push({ stream: 'meta', text: `▶ Pipeline: ${pipeline.name}`, ts: new Date().toISOString() });

    const res = await fetch(`/sites/${props.site.id}/deploy/pipeline/${pipeline.id}`, {
        method: 'POST',
        headers: { 'X-XSRF-TOKEN': getCsrf(), Accept: 'application/json' },
    });
    const json = await res.json();
    if (!res.ok || json.error) {
        terminalLines.value.push({ stream: 'stderr', text: json.error ?? 'Request failed', ts: new Date().toISOString() });
        runStatus.value = 'error';
        return;
    }
    connectStream(json.exec_id);
}

// ── Presets ───────────────────────────────────────────────────────────────────

const showPresetForm = ref(false);
const newPreset = ref({ label: '', command: 'php', args: '' });

async function savePreset() {
    if (!newPreset.value.label || !newPreset.value.args.trim()) return;
    const args = newPreset.value.args.trim().split(/\s+/);
    const res = await fetch(`/sites/${props.site.id}/presets`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getCsrf(), Accept: 'application/json' },
        body: JSON.stringify({ label: newPreset.value.label, command: newPreset.value.command, args }),
    });
    if (res.ok) {
        showPresetForm.value = false;
        newPreset.value = { label: '', command: 'php', args: '' };
        router.reload({ only: ['presets'] });
    }
}

async function deletePreset(id: number) {
    await fetch(`/sites/${props.site.id}/presets/${id}`, {
        method: 'DELETE',
        headers: { 'X-XSRF-TOKEN': getCsrf() },
    });
    router.reload({ only: ['presets'] });
}

async function runPreset(preset: Preset) {
    clearTerminal();
    activeAction.value = preset.label;
    terminalLines.value.push({ stream: 'meta', text: `▶ ${preset.label}: ${preset.command} ${preset.args.join(' ')}`, ts: new Date().toISOString() });
    const res = await fetch(`/sites/${props.site.id}/deploy/custom`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getCsrf(), Accept: 'application/json' },
        body: JSON.stringify({ command: preset.command, args: preset.args }),
    });
    const json = await res.json();
    if (!res.ok || json.error) {
        terminalLines.value.push({ stream: 'stderr', text: json.error ?? 'Request failed', ts: new Date().toISOString() });
        runStatus.value = 'error';
        return;
    }
    connectStream(json.exec_id);
}

// ── Tab state ─────────────────────────────────────────────────────────────────
const activeTab = ref<'quick' | 'pipelines' | 'custom'>('quick');

// ── Helpers ───────────────────────────────────────────────────────────────────

function getCsrf(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'success') return 'default';
    if (status === 'failed') return 'destructive';
    if (status === 'running') return 'outline';
    return 'secondary';
}

function lineClass(line: LogLine): string {
    if (line.stream === 'stderr') return 'text-red-400';
    if (line.stream === 'meta') return 'text-yellow-400 font-semibold';
    return 'text-green-300';
}

function terminalStatusIcon() {
    if (runStatus.value === 'running') return Circle;
    if (runStatus.value === 'success') return CheckCircle2;
    if (runStatus.value === 'error') return XCircle;
    return Terminal;
}

function terminalStatusColor() {
    if (runStatus.value === 'running') return 'text-yellow-400 animate-pulse';
    if (runStatus.value === 'success') return 'text-green-400';
    if (runStatus.value === 'error') return 'text-red-400';
    return 'text-muted-foreground';
}
</script>

<template>
    <Head :title="`Deploy — ${site.domain}`" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <Heading
                :title="`Deploy — ${site.domain}`"
                :description="`${site.path} · ${site.server.name}`"
            />
            <div class="flex gap-2">
                <Badge variant="outline" class="font-mono text-xs">
                    <GitBranch class="mr-1 size-3" />{{ site.branch }}
                </Badge>
                <Badge variant="outline" class="text-xs">
                    <Server class="mr-1 size-3" />{{ site.server.name }}
                </Badge>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
            <!-- Left column: controls -->
            <div class="xl:col-span-2 space-y-6">

                <!-- Tab bar -->
                <div class="flex rounded-lg border bg-muted p-1 gap-1">
                    <button
                        v-for="tab in ([{ key: 'quick', label: 'Quick', icon: Zap }, { key: 'pipelines', label: 'Pipelines', icon: Layers }, { key: 'custom', label: 'Custom', icon: Code2 }] as const)"
                        :key="tab.key"
                        :class="[
                            'flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-all',
                            activeTab === tab.key
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground',
                        ]"
                        @click="activeTab = tab.key"
                    >
                        <component :is="tab.icon" class="size-3.5" />{{ tab.label }}
                    </button>
                </div>

                    <!-- Quick actions tab -->
                    <div v-if="activeTab === 'quick'" class="mt-4 space-y-3">
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                v-for="qa in quickActions"
                                :key="qa.key"
                                :disabled="isRunning"
                                class="group flex flex-col items-start gap-1 rounded-xl border bg-card p-3 text-left transition-all hover:border-primary hover:bg-primary/5 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="runQuickAction(qa.key, qa.label)"
                            >
                                <div class="flex items-center gap-2">
                                    <component
                                        :is="qa.icon"
                                        class="size-4 text-muted-foreground group-hover:text-primary transition-colors"
                                    />
                                    <span class="text-sm font-medium">{{ qa.label }}</span>
                                </div>
                                <p class="text-xs text-muted-foreground">{{ qa.description }}</p>
                            </button>
                        </div>

                        <!-- Artisan command runner -->
                        <Card>
                            <CardHeader class="pb-3">
                                <CardTitle class="text-sm flex items-center gap-2">
                                    <Terminal class="size-4" />Artisan Command
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="flex gap-2">
                                    <div class="flex items-center rounded-md border bg-muted px-3 text-sm text-muted-foreground shrink-0">
                                        php artisan
                                    </div>
                                    <Input
                                        v-model="artisanCmd"
                                        placeholder="migrate:status"
                                        class="font-mono text-sm"
                                        :disabled="isRunning"
                                        @keydown.enter="runArtisan"
                                    />
                                    <Button size="sm" :disabled="isRunning || !artisanCmd" @click="runArtisan">
                                        <Play class="size-3.5" />
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Saved presets -->
                        <Card v-if="presets.length > 0 || showPresetForm">
                            <CardHeader class="pb-3">
                                <div class="flex items-center justify-between">
                                    <CardTitle class="text-sm flex items-center gap-2">
                                        <Settings class="size-4" />Saved Presets
                                    </CardTitle>
                                    <Button size="sm" variant="ghost" @click="showPresetForm = !showPresetForm">
                                        <Plus class="size-3.5" />
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent class="space-y-2">
                                <div
                                    v-for="preset in presets"
                                    :key="preset.id"
                                    class="flex items-center justify-between gap-2 rounded-lg border px-3 py-2"
                                >
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium truncate">{{ preset.label }}</p>
                                        <p class="text-xs text-muted-foreground font-mono truncate">{{ preset.command }} {{ preset.args.join(' ') }}</p>
                                    </div>
                                    <div class="flex gap-1 shrink-0">
                                        <Button size="sm" variant="ghost" :disabled="isRunning" @click="runPreset(preset)">
                                            <Play class="size-3" />
                                        </Button>
                                        <Button size="sm" variant="ghost" class="text-destructive" @click="deletePreset(preset.id)">
                                            <Trash2 class="size-3" />
                                        </Button>
                                    </div>
                                </div>

                                <!-- Add preset form -->
                                <div v-if="showPresetForm" class="space-y-2 rounded-lg border p-3 bg-muted/40">
                                    <Input v-model="newPreset.label" placeholder="Label (e.g. Clear Cache)" class="text-sm" />
                                    <div class="flex gap-2">
                                        <select
                                            v-model="newPreset.command"
                                            class="rounded-md border bg-background px-2 py-1 text-sm"
                                        >
                                            <option v-for="b in customBins" :key="b" :value="b">{{ b }}</option>
                                        </select>
                                        <Input v-model="newPreset.args" placeholder="artisan cache:clear" class="text-sm font-mono" />
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <Button size="sm" variant="ghost" @click="showPresetForm = false">Cancel</Button>
                                        <Button size="sm" @click="savePreset">Save</Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Button
                            v-if="presets.length === 0 && !showPresetForm"
                            variant="outline"
                            size="sm"
                            class="w-full"
                            @click="showPresetForm = true"
                        >
                            <Plus class="mr-1.5 size-3.5" />Add Command Preset
                        </Button>
                    </div>

                    <!-- Pipelines tab -->
                    <div v-if="activeTab === 'pipelines'" class="mt-4 space-y-3">
                        <div v-if="pipelines.length === 0 && !showPipelineForm" class="rounded-xl border border-dashed p-8 text-center">
                            <Layers class="mx-auto mb-3 size-8 text-muted-foreground/50" />
                            <p class="text-sm text-muted-foreground">No pipelines yet.</p>
                            <p class="text-xs text-muted-foreground mt-1">Chain multiple steps into a repeatable pipeline.</p>
                        </div>

                        <div v-for="pipeline in pipelines" :key="pipeline.id" class="rounded-xl border bg-card">
                            <div class="flex items-center justify-between p-4">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="font-medium text-sm">{{ pipeline.name }}</p>
                                        <Badge v-if="pipeline.is_default" variant="secondary" class="text-xs">default</Badge>
                                    </div>
                                    <p v-if="pipeline.description" class="text-xs text-muted-foreground mt-0.5">{{ pipeline.description }}</p>
                                    <div class="flex items-center gap-1 mt-1.5 flex-wrap">
                                        <template v-for="(step, i) in pipeline.steps" :key="step">
                                            <span class="text-xs bg-muted rounded px-1.5 py-0.5">{{ pipelineSteps[step] ?? step }}</span>
                                            <ChevronRight v-if="i < pipeline.steps.length - 1" class="size-3 text-muted-foreground" />
                                        </template>
                                    </div>
                                </div>
                                <div class="flex gap-1.5 shrink-0 ml-3">
                                    <Button size="sm" :disabled="isRunning" @click="runPipeline(pipeline)">
                                        <Play class="mr-1.5 size-3.5" />Run
                                    </Button>
                                    <Button size="sm" variant="ghost" class="text-destructive" @click="deletePipeline(pipeline.id)">
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <!-- New pipeline form -->
                        <div v-if="showPipelineForm" class="rounded-xl border p-4 space-y-4 bg-muted/30">
                            <h4 class="font-medium text-sm">New Pipeline</h4>
                            <div class="space-y-2">
                                <Input v-model="newPipeline.name" placeholder="Pipeline name" />
                                <Input v-model="newPipeline.description" placeholder="Description (optional)" />
                                <Input v-model="newPipeline.branch" :placeholder="`Branch (default: ${site.branch})`" />
                            </div>
                            <div>
                                <Label class="text-xs text-muted-foreground mb-2 block">Steps (in order)</Label>
                                <div class="grid grid-cols-2 gap-1.5">
                                    <button
                                        v-for="(label, key) in pipelineSteps"
                                        :key="key"
                                        :class="[
                                            'rounded-lg border px-3 py-2 text-left text-xs transition-all',
                                            newPipeline.steps.includes(key)
                                                ? 'border-primary bg-primary/10 text-primary font-medium'
                                                : 'border-border hover:border-muted-foreground',
                                        ]"
                                        @click="toggleStep(key)"
                                    >
                                        <span v-if="newPipeline.steps.includes(key)" class="mr-1 font-bold">{{ newPipeline.steps.indexOf(key) + 1 }}.</span>
                                        {{ label }}
                                    </button>
                                </div>
                            </div>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" v-model="newPipeline.is_default" class="rounded" />
                                Set as default pipeline
                            </label>
                            <p v-if="pipelineError" class="text-xs text-destructive">{{ pipelineError }}</p>
                            <div class="flex justify-end gap-2">
                                <Button size="sm" variant="ghost" @click="showPipelineForm = false">Cancel</Button>
                                <Button size="sm" @click="savePipeline">Save Pipeline</Button>
                            </div>
                        </div>

                        <Button variant="outline" size="sm" class="w-full" @click="showPipelineForm = true">
                            <Plus class="mr-1.5 size-3.5" />New Pipeline
                        </Button>
                    </div>

                    <!-- Custom command tab -->
                    <div v-if="activeTab === 'custom'" class="mt-4">
                        <Card>
                            <CardHeader class="pb-3">
                                <CardTitle class="text-sm">Run Custom Command</CardTitle>
                                <CardDescription class="text-xs">
                                    Whitelisted binaries only: {{ customBins.join(', ') }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div class="flex gap-2">
                                    <select
                                        v-model="customBin"
                                        class="rounded-md border bg-background px-3 py-2 text-sm"
                                        :disabled="isRunning"
                                    >
                                        <option v-for="b in customBins" :key="b" :value="b">{{ b }}</option>
                                    </select>
                                    <Input
                                        v-model="customArgs"
                                        placeholder="install --no-dev"
                                        class="font-mono text-sm"
                                        :disabled="isRunning"
                                        @keydown.enter="runCustom"
                                    />
                                </div>
                                <div class="rounded-md bg-muted/50 px-3 py-2 text-xs font-mono text-muted-foreground">
                                    <span class="text-green-600 dark:text-green-400">$</span>
                                    {{ customBin }} {{ customArgs || '<args>' }}
                                </div>
                                <Button class="w-full" :disabled="isRunning || !customArgs.trim()" @click="runCustom">
                                    <Play class="mr-2 size-4" />Execute
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
            </div>

            <!-- Right column: terminal + history -->
            <div class="xl:col-span-3 space-y-6">

                <!-- Live terminal -->
                <Card class="flex flex-col">
                    <CardHeader class="pb-3 border-b">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <!-- Traffic lights -->
                                <div class="flex gap-1.5">
                                    <div class="size-3 rounded-full bg-red-500/80" />
                                    <div class="size-3 rounded-full bg-yellow-500/80" />
                                    <div class="size-3 rounded-full bg-green-500/80" />
                                </div>
                                <component
                                    :is="terminalStatusIcon()"
                                    class="size-4 transition-colors"
                                    :class="terminalStatusColor()"
                                />
                                <span class="text-sm font-medium font-mono">
                                    {{ activeAction || 'terminal' }}
                                </span>
                                <Badge
                                    v-if="runStatus !== 'idle'"
                                    :variant="runStatus === 'success' ? 'default' : runStatus === 'error' ? 'destructive' : 'outline'"
                                    class="text-xs"
                                >
                                    {{ runStatus === 'running' ? 'running…' : runStatus }}
                                </Badge>
                            </div>
                            <Button
                                size="sm"
                                variant="ghost"
                                class="text-muted-foreground hover:text-foreground"
                                :disabled="isRunning"
                                @click="clearTerminal"
                            >
                                <RotateCcw class="size-3.5 mr-1" />Clear
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div
                            ref="terminalEl"
                            class="h-[420px] overflow-y-auto bg-zinc-950 dark:bg-black rounded-b-xl p-4 font-mono text-xs leading-relaxed"
                        >
                            <div v-if="terminalLines.length === 0" class="flex h-full items-center justify-center text-zinc-600">
                                <div class="text-center">
                                    <Terminal class="mx-auto mb-3 size-8 opacity-30" />
                                    <p>Select a command to run</p>
                                    <p class="text-xs mt-1 opacity-60">Output will stream here live</p>
                                </div>
                            </div>
                            <div v-for="(line, i) in terminalLines" :key="i" class="flex gap-2">
                                <span class="shrink-0 text-zinc-600 select-none">
                                    {{ String(i + 1).padStart(3, ' ') }}
                                </span>
                                <span :class="lineClass(line)" class="break-all whitespace-pre-wrap">{{ line.text }}</span>
                            </div>
                            <!-- Blinking cursor when running -->
                            <div v-if="isRunning" class="flex gap-2 mt-1">
                                <span class="shrink-0 text-zinc-600 select-none">   </span>
                                <span class="text-green-400 animate-pulse">▋</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Deployment history -->
                <Card>
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-sm flex items-center gap-2">
                                <Clock class="size-4" />Deployment History
                            </CardTitle>
                            <a
                                :href="`/sites/${site.id}/deployments`"
                                class="text-xs text-muted-foreground hover:text-foreground transition-colors"
                            >
                                View all →
                            </a>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="deployments.length === 0" class="text-center text-sm text-muted-foreground py-6">
                            No deployments yet.
                        </div>
                        <div v-else class="space-y-1">
                            <a
                                v-for="d in deployments"
                                :key="d.id"
                                :href="deploymentRoutes.show(d)"
                                class="flex items-center justify-between rounded-lg px-3 py-2.5 hover:bg-muted/60 transition-colors group"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <div
                                        class="size-2 rounded-full shrink-0"
                                        :class="{
                                            'bg-green-500': d.status === 'success',
                                            'bg-red-500': d.status === 'failed',
                                            'bg-yellow-500 animate-pulse': d.status === 'running',
                                            'bg-zinc-400': d.status === 'queued',
                                        }"
                                    />
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium truncate">
                                            #{{ d.id }}
                                            <span class="text-muted-foreground font-normal">· {{ d.branch ?? site.branch }}</span>
                                        </p>
                                        <p class="text-xs text-muted-foreground">{{ d.created_at }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <Badge :variant="statusVariant(d.status)" class="text-xs">{{ d.status }}</Badge>
                                    <ChevronRight class="size-3.5 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity" />
                                </div>
                            </a>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
