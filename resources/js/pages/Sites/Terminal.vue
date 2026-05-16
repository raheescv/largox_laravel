<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    CheckCircle2,
    ChevronRight,
    Circle,
    Clock,
    GitBranch,
    GitPullRequest,
    Layers,
    PackageCheck,
    PackageOpen,
    Play,
    RefreshCw,
    RotateCcw,
    Terminal,
    XCircle,
    Zap,
} from 'lucide-vue-next';
import { nextTick, onBeforeUnmount, ref } from 'vue';
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
type LogLine = { stream: 'stdout' | 'stderr' | 'meta'; text: string; ts: string };
type HistoryEntry = { label: string; action: string; payload: Record<string, unknown>; lines: LogLine[]; status: 'success' | 'error' | 'running' };

// ── Props ─────────────────────────────────────────────────────────────────────

defineOptions({
    layout: (pageProps: { site: SiteModel }) => ({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Servers', href: serverRoutes.index() },
            { title: pageProps.site.server.name, href: serverRoutes.show(pageProps.site.server) },
            { title: 'Sites', href: serverSiteRoutes.index(pageProps.site.server) },
            { title: pageProps.site.domain, href: siteRoutes.show(pageProps.site) },
            { title: 'Terminal', href: '#' },
        ],
    }),
});

const props = defineProps<{ site: SiteModel }>();

// ── Terminal state ────────────────────────────────────────────────────────────

const lines = ref<LogLine[]>([]);
const terminalEl = ref<HTMLElement | null>(null);
const isRunning = ref(false);
const runStatus = ref<'idle' | 'running' | 'success' | 'error'>('idle');
const activeLabel = ref('');
const history = ref<HistoryEntry[]>([]);
let evtSource: EventSource | null = null;
let streamDone = false; // guard: prevents onerror from firing after a clean done event

// ── Custom input ──────────────────────────────────────────────────────────────

const customBin = ref('php');
const customArgs = ref('');
const artisanCmd = ref('');
const inputMode = ref<'artisan' | 'custom'>('artisan');
const BINS = ['composer', 'php', 'git', 'npm', 'yarn', 'node'] as const;

// ── Quick commands ────────────────────────────────────────────────────────────

const quickCmds = [
    {
        group: 'Git',
        icon: GitBranch,
        items: [
            { key: 'git_pull', label: 'git pull', description: 'Pull latest changes', icon: GitPullRequest },
        ],
    },
    {
        group: 'Composer',
        icon: PackageCheck,
        items: [
            { key: 'composer_install', label: 'composer install', description: 'Install dependencies (no-dev)', icon: PackageCheck },
            { key: 'composer_update', label: 'composer update', description: 'Update all packages', icon: PackageOpen },
        ],
    },
    {
        group: 'Artisan',
        icon: Zap,
        items: [
            { key: 'artisan_migrate', label: 'migrate --force', description: 'Run pending migrations', icon: Layers },
            { key: 'artisan_optimize', label: 'optimize', description: 'Cache config, routes, views', icon: Zap },
            { key: 'restart_queue', label: 'queue:restart', description: 'Signal workers to restart', icon: RefreshCw },
        ],
    },
] as const;

// ── Core: run a command ───────────────────────────────────────────────────────

async function runAction(label: string, streamAction: string, payload: Record<string, unknown>) {
    if (isRunning.value) return;

    lines.value = [];
    isRunning.value = true;
    runStatus.value = 'running';
    activeLabel.value = label;

    emit({ stream: 'meta', text: `▶  ${label}`, ts: now() });
    emit({ stream: 'meta', text: `   path: ${props.site.path}`, ts: now() });

    let execId: string;
    try {
        const res = await post(`/sites/${props.site.id}/deploy/quick`, { action: streamAction });
        if (res?.error) throw new Error(res.error);
        if (!res?.exec_id) throw new Error('No exec_id returned — is the Go agent running and configured?');
        execId = res.exec_id;
    } catch (e: unknown) {
        const msg = e instanceof Error ? e.message : String(e);
        emit({ stream: 'stderr', text: `Error: ${msg}`, ts: now() });
        finish('error');
        return;
    }

    connectStream(execId, label);
}

async function runStreamAction(label: string, url: string, body: Record<string, unknown>) {
    if (isRunning.value) return;

    lines.value = [];
    isRunning.value = true;
    runStatus.value = 'running';
    activeLabel.value = label;

    emit({ stream: 'meta', text: `▶  ${label}`, ts: now() });

    let execId: string;
    try {
        const res = await post(url, body);
        if (res?.error) throw new Error(res.error);
        if (!res?.exec_id) throw new Error('No exec_id returned — is the Go agent running and configured?');
        execId = res.exec_id;
    } catch (e: unknown) {
        const msg = e instanceof Error ? e.message : String(e);
        emit({ stream: 'stderr', text: `Error: ${msg}`, ts: now() });
        finish('error');
        return;
    }

    connectStream(execId, label);
}

function connectStream(execId: string, label: string) {
    if (evtSource) { evtSource.close(); evtSource = null; }
    streamDone = false;

    evtSource = new EventSource(`/sites/${props.site.id}/deploy/stream/${execId}`);

    evtSource.addEventListener('log', (e: MessageEvent) => {
        try {
            const line = JSON.parse(e.data) as LogLine;
            emit(line);
        } catch {}
    });

    evtSource.addEventListener('done', () => {
        streamDone = true;
        evtSource?.close();
        evtSource = null;

        const lastMeta = [...lines.value].reverse().find((l) => l.stream === 'meta');
        const status = lastMeta?.text.startsWith('✗') ? 'error' : 'success';

        emit({ stream: 'meta', text: status === 'success' ? '✓  Done' : '✗  Failed', ts: now() });

        history.value.unshift({ label, action: label, payload: {}, lines: [...lines.value], status });
        if (history.value.length > 20) history.value.pop();

        finish(status);
    });

    evtSource.addEventListener('error', (e: MessageEvent) => {
        // Server-sent error event (our explicit event: error from the proxy)
        if (streamDone) return;
        streamDone = true;
        evtSource?.close();
        evtSource = null;
        try {
            const data = JSON.parse(e.data ?? '{}');
            emit({ stream: 'stderr', text: `Agent error: ${data.error ?? 'unknown'}`, ts: now() });
        } catch {}
        finish('error');
    });

    evtSource.onerror = () => {
        // Network/HTTP error — only fires if we haven't cleanly finished
        if (streamDone) return;
        streamDone = true;
        evtSource?.close();
        evtSource = null;
        emit({ stream: 'stderr', text: '✗  Stream connection failed. Check that the Go agent is running and reachable.', ts: now() });
        finish('error');
    };
}

function emit(line: LogLine) {
    lines.value.push(line);
    nextTick(() => {
        if (terminalEl.value) terminalEl.value.scrollTop = terminalEl.value.scrollHeight;
    });
}

function finish(status: 'success' | 'error') {
    isRunning.value = false;
    runStatus.value = status;
}

// ── Quick action helpers ──────────────────────────────────────────────────────

function runQuick(key: string, label: string) {
    runAction(label, key, { path: props.site.path });
}

function runArtisan() {
    const cmd = artisanCmd.value.trim();
    if (!cmd) return;
    runStreamAction(
        `php artisan ${cmd}`,
        `/sites/${props.site.id}/deploy/artisan`,
        { command: cmd },
    );
}

function runCustom() {
    const args = customArgs.value.trim();
    if (!args) return;
    runStreamAction(
        `${customBin.value} ${args}`,
        `/sites/${props.site.id}/deploy/custom`,
        { command: customBin.value, args: args.split(/\s+/) },
    );
}

function loadHistoryEntry(entry: HistoryEntry) {
    lines.value = entry.lines;
    runStatus.value = entry.status;
    activeLabel.value = entry.label;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function clearTerminal() {
    if (isRunning.value) return;
    lines.value = [];
    runStatus.value = 'idle';
    activeLabel.value = '';
}

function now() { return new Date().toISOString(); }

async function post(url: string, body: Record<string, unknown>) {
    const csrf = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '';
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': decodeURIComponent(csrf),
            Accept: 'application/json',
        },
        body: JSON.stringify(body),
    });
    return res.json();
}

function lineClass(line: LogLine): string {
    if (line.stream === 'stderr') return 'text-red-400';
    if (line.stream === 'meta') return 'text-yellow-300 font-semibold';
    return 'text-emerald-300';
}

onBeforeUnmount(() => evtSource?.close());
</script>

<template>
    <Head :title="`Terminal — ${site.domain}`" />

    <div class="flex h-[calc(100vh-8rem)] gap-4 overflow-hidden">

        <!-- ── Left sidebar: commands ───────────────────────────────────── -->
        <aside class="w-64 shrink-0 flex flex-col gap-4 overflow-y-auto">

            <!-- Site info badge -->
            <div class="rounded-xl border bg-card p-3">
                <div class="flex items-center gap-2 text-sm font-semibold truncate">
                    <Terminal class="size-4 shrink-0 text-primary" />
                    {{ site.domain }}
                </div>
                <div class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                    <GitBranch class="size-3" />{{ site.branch }}
                    <span class="mx-1 opacity-40">·</span>
                    {{ site.server.name }}
                </div>
                <p class="mt-1 text-[10px] font-mono text-muted-foreground/60 truncate">{{ site.path }}</p>
            </div>

            <!-- Quick commands -->
            <div v-for="group in quickCmds" :key="group.group" class="space-y-1">
                <p class="px-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground/60">
                    {{ group.group }}
                </p>
                <button
                    v-for="item in group.items"
                    :key="item.key"
                    :disabled="isRunning"
                    class="group flex w-full items-start gap-3 rounded-lg border border-transparent px-3 py-2.5 text-left transition-all hover:border-border hover:bg-muted/60 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="runQuick(item.key, item.label)"
                >
                    <component :is="item.icon" class="mt-0.5 size-3.5 shrink-0 text-muted-foreground group-hover:text-foreground" />
                    <div class="min-w-0">
                        <p class="text-xs font-mono font-medium">{{ item.label }}</p>
                        <p class="text-[10px] text-muted-foreground">{{ item.description }}</p>
                    </div>
                </button>
            </div>

            <!-- History -->
            <div v-if="history.length > 0" class="space-y-1">
                <p class="px-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground/60">
                    History
                </p>
                <button
                    v-for="(entry, i) in history"
                    :key="i"
                    class="group flex w-full items-center gap-2 rounded-lg border border-transparent px-3 py-2 text-left transition-all hover:border-border hover:bg-muted/60"
                    @click="loadHistoryEntry(entry)"
                >
                    <component
                        :is="entry.status === 'success' ? CheckCircle2 : XCircle"
                        class="size-3 shrink-0"
                        :class="entry.status === 'success' ? 'text-green-500' : 'text-red-500'"
                    />
                    <span class="truncate text-xs font-mono text-muted-foreground group-hover:text-foreground">
                        {{ entry.label }}
                    </span>
                </button>
            </div>
        </aside>

        <!-- ── Main terminal area ───────────────────────────────────────── -->
        <div class="flex flex-1 flex-col gap-3 min-w-0 overflow-hidden">

            <!-- Terminal window -->
            <div class="flex flex-1 flex-col rounded-xl border overflow-hidden bg-zinc-950 dark:bg-black shadow-2xl">

                <!-- Title bar -->
                <div class="flex items-center gap-3 border-b border-zinc-800 bg-zinc-900 px-4 py-2.5 shrink-0">
                    <!-- Traffic lights -->
                    <div class="flex gap-1.5">
                        <div class="size-3 rounded-full bg-red-500/80" />
                        <div class="size-3 rounded-full bg-yellow-500/80" />
                        <div class="size-3 rounded-full bg-green-500/80" />
                    </div>

                    <!-- Status indicator -->
                    <div class="flex items-center gap-2 flex-1 min-w-0">
                        <component
                            :is="runStatus === 'running' ? Circle : runStatus === 'success' ? CheckCircle2 : runStatus === 'error' ? XCircle : Terminal"
                            class="size-3.5 shrink-0 transition-colors"
                            :class="{
                                'text-yellow-400 animate-pulse': runStatus === 'running',
                                'text-green-400': runStatus === 'success',
                                'text-red-400': runStatus === 'error',
                                'text-zinc-500': runStatus === 'idle',
                            }"
                        />
                        <span class="text-xs font-mono text-zinc-400 truncate">
                            {{ activeLabel || `${site.domain} — ${site.path}` }}
                        </span>
                    </div>

                    <!-- Controls -->
                    <div class="flex items-center gap-2 shrink-0">
                        <span v-if="runStatus !== 'idle'" class="text-[10px] font-mono px-2 py-0.5 rounded"
                            :class="{
                                'bg-yellow-500/20 text-yellow-400': runStatus === 'running',
                                'bg-green-500/20 text-green-400': runStatus === 'success',
                                'bg-red-500/20 text-red-400': runStatus === 'error',
                            }"
                        >{{ runStatus }}</span>
                        <button
                            :disabled="isRunning"
                            class="rounded px-2 py-1 text-[10px] font-mono text-zinc-500 hover:bg-zinc-800 hover:text-zinc-300 disabled:cursor-not-allowed transition-colors"
                            @click="clearTerminal"
                        >
                            clear
                        </button>
                    </div>
                </div>

                <!-- Output area -->
                <div
                    ref="terminalEl"
                    class="flex-1 overflow-y-auto p-4 font-mono text-xs leading-relaxed"
                    style="scrollbar-color: #3f3f46 transparent;"
                >
                    <!-- Empty state -->
                    <div v-if="lines.length === 0" class="flex h-full items-center justify-center">
                        <div class="text-center">
                            <Terminal class="mx-auto mb-4 size-10 text-zinc-700" />
                            <p class="text-zinc-500 text-sm">No output yet</p>
                            <p class="text-zinc-700 text-xs mt-1">Select a command from the sidebar or type below</p>
                        </div>
                    </div>

                    <!-- Log lines -->
                    <div v-for="(line, i) in lines" :key="i" class="flex gap-3 group">
                        <span class="select-none text-zinc-700 w-8 text-right shrink-0 group-hover:text-zinc-600 transition-colors">
                            {{ i + 1 }}
                        </span>
                        <span :class="lineClass(line)" class="break-all whitespace-pre-wrap flex-1">{{ line.text }}</span>
                    </div>

                    <!-- Blinking cursor -->
                    <div v-if="isRunning" class="flex gap-3 mt-0.5">
                        <span class="select-none text-zinc-700 w-8 text-right shrink-0" />
                        <span class="text-emerald-400 animate-pulse select-none">▋</span>
                    </div>
                </div>

                <!-- ── Input bar ─────────────────────────────────────────── -->
                <div class="border-t border-zinc-800 bg-zinc-900 px-4 py-3 space-y-2 shrink-0">

                    <!-- Mode switch -->
                    <div class="flex gap-1">
                        <button
                            :class="[
                                'rounded px-2 py-0.5 text-[10px] font-mono transition-colors',
                                inputMode === 'artisan'
                                    ? 'bg-primary/20 text-primary'
                                    : 'text-zinc-500 hover:text-zinc-300',
                            ]"
                            @click="inputMode = 'artisan'"
                        >php artisan</button>
                        <button
                            :class="[
                                'rounded px-2 py-0.5 text-[10px] font-mono transition-colors',
                                inputMode === 'custom'
                                    ? 'bg-primary/20 text-primary'
                                    : 'text-zinc-500 hover:text-zinc-300',
                            ]"
                            @click="inputMode = 'custom'"
                        >custom</button>
                    </div>

                    <!-- Artisan input -->
                    <div v-if="inputMode === 'artisan'" class="flex items-center gap-2">
                        <span class="shrink-0 font-mono text-xs text-emerald-500 select-none">
                            {{ site.domain }} <span class="text-zinc-500">$</span> php artisan
                        </span>
                        <input
                            v-model="artisanCmd"
                            :disabled="isRunning"
                            placeholder="migrate:status"
                            class="flex-1 bg-transparent font-mono text-xs text-zinc-200 placeholder-zinc-700 outline-none disabled:opacity-40"
                            @keydown.enter="runArtisan"
                        />
                        <button
                            :disabled="isRunning || !artisanCmd.trim()"
                            class="shrink-0 rounded bg-primary/20 p-1.5 text-primary transition-all hover:bg-primary/30 disabled:cursor-not-allowed disabled:opacity-30"
                            @click="runArtisan"
                        >
                            <Play class="size-3" />
                        </button>
                    </div>

                    <!-- Custom command input -->
                    <div v-if="inputMode === 'custom'" class="flex items-center gap-2">
                        <span class="shrink-0 font-mono text-xs text-emerald-500 select-none">
                            {{ site.domain }} <span class="text-zinc-500">$</span>
                        </span>
                        <select
                            v-model="customBin"
                            :disabled="isRunning"
                            class="shrink-0 bg-transparent font-mono text-xs text-primary outline-none disabled:opacity-40"
                        >
                            <option v-for="b in BINS" :key="b" :value="b" class="bg-zinc-900">{{ b }}</option>
                        </select>
                        <input
                            v-model="customArgs"
                            :disabled="isRunning"
                            placeholder="install --no-dev"
                            class="flex-1 bg-transparent font-mono text-xs text-zinc-200 placeholder-zinc-700 outline-none disabled:opacity-40"
                            @keydown.enter="runCustom"
                        />
                        <button
                            :disabled="isRunning || !customArgs.trim()"
                            class="shrink-0 rounded bg-primary/20 p-1.5 text-primary transition-all hover:bg-primary/30 disabled:cursor-not-allowed disabled:opacity-30"
                            @click="runCustom"
                        >
                            <Play class="size-3" />
                        </button>
                    </div>

                    <p class="text-[10px] font-mono text-zinc-700">
                        Whitelisted: {{ BINS.join(', ') }} · Press <kbd class="rounded bg-zinc-800 px-1 text-zinc-500">↵ Enter</kbd> to run
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
