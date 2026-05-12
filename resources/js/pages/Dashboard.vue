<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    Clock3,
    Cpu,
    Database,
    HardDrive,
    MemoryStick,
    RefreshCw,
    Server,
    TerminalSquare,
    Wifi,
    WifiOff,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';

interface MemoryMetrics {
    total_kb: number;
    available_kb: number;
    used_kb: number;
    used_pct: number;
    swap_total_kb: number;
    swap_free_kb: number;
}

interface DiskMetric {
    mount: string;
    total_gb: number;
    free_gb: number;
    used_gb: number;
    used_pct: number;
}

interface SystemMetrics {
    hostname: string;
    os: string;
    arch: string;
    uptime_sec: number;
    load_avg: [number, number, number];
    cpu_count: number;
    memory: MemoryMetrics;
    disk: DiskMetric[];
    time: string;
}

interface AuditLog {
    id: number;
    action: string;
    status: string;
    created_at: string;
    server?: { name: string } | null;
}

const props = defineProps<{
    server: { id: number; name: string; host: string; status: string } | null;
    health: { reachable: boolean } | null;
    metrics: SystemMetrics | null;
    error: string | null;
    recent_logs: AuditLog[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const reachable = computed(() => props.health?.reachable === true);
const refreshSeconds = 5;

const uptimeHuman = computed(() => {
    const s = props.metrics?.uptime_sec ?? 0;
    const d = Math.floor(s / 86400);
    const h = Math.floor((s % 86400) / 3600);
    const m = Math.floor((s % 3600) / 60);
    return `${d}d ${h}h ${m}m`;
});

const memUsedGB = computed(() =>
    props.metrics
        ? (props.metrics.memory.used_kb / 1024 / 1024).toFixed(2)
        : '0',
);
const memTotalGB = computed(() =>
    props.metrics
        ? (props.metrics.memory.total_kb / 1024 / 1024).toFixed(2)
        : '0',
);
const memAvailableGB = computed(() =>
    props.metrics
        ? (props.metrics.memory.available_kb / 1024 / 1024).toFixed(2)
        : '0',
);
const swapUsedPct = computed(() => {
    const memory = props.metrics?.memory;

    if (!memory?.swap_total_kb) return 0;

    return Math.max(
        0,
        Math.min(
            100,
            ((memory.swap_total_kb - memory.swap_free_kb) /
                memory.swap_total_kb) *
                100,
        ),
    );
});
const cpuPressure = computed(() => {
    if (!props.metrics?.cpu_count) return 0;

    return Math.min(
        100,
        Math.max(
            0,
            (props.metrics.load_avg[0] / props.metrics.cpu_count) * 100,
        ),
    );
});
const loadRows = computed(() => {
    const metrics = props.metrics;

    if (!metrics) return [];

    return [
        { label: '1m', value: metrics.load_avg[0] },
        { label: '5m', value: metrics.load_avg[1] },
        { label: '15m', value: metrics.load_avg[2] },
    ].map((row) => ({
        ...row,
        pct: Math.min(100, Math.max(0, (row.value / metrics.cpu_count) * 100)),
    }));
});
const busiestDisk = computed(() => {
    const disks = props.metrics?.disk ?? [];

    return [...disks].sort((a, b) => b.used_pct - a.used_pct)[0] ?? null;
});
const diskWarnings = computed(
    () => props.metrics?.disk.filter((disk) => disk.used_pct >= 80).length ?? 0,
);
const healthTone = computed(() => {
    if (!props.server) return 'secondary';
    if (!reachable.value) return 'destructive';
    if (
        cpuPressure.value >= 90 ||
        (props.metrics?.memory.used_pct ?? 0) >= 90
    ) {
        return 'destructive';
    }
    if (
        cpuPressure.value >= 75 ||
        (props.metrics?.memory.used_pct ?? 0) >= 75 ||
        diskWarnings.value > 0
    ) {
        return 'secondary';
    }

    return 'default';
});
const healthLabel = computed(() => {
    if (!props.server) return 'No server';
    if (!reachable.value) return 'Agent offline';
    if (healthTone.value === 'destructive') return 'High pressure';
    if (healthTone.value === 'secondary') return 'Watch';

    return 'Healthy';
});
const terminalLines = computed(() => {
    if (!props.metrics) return [];

    return [
        `host=${props.metrics.hostname}`,
        `os=${props.metrics.os}/${props.metrics.arch}`,
        `agent=${reachable.value ? 'online' : 'unreachable'}`,
        `load=${props.metrics.load_avg.map((load) => load.toFixed(2)).join(' ')}`,
        `memory=${props.metrics.memory.used_pct.toFixed(1)}% used`,
        `disk=${busiestDisk.value ? `${busiestDisk.value.mount} ${busiestDisk.value.used_pct.toFixed(1)}%` : 'none'}`,
        `sample=${props.metrics.time}`,
    ];
});

let timer: ReturnType<typeof setInterval> | null = null;
onMounted(() => {
    timer = setInterval(
        () => router.reload({ only: ['metrics', 'health'] }),
        refreshSeconds * 1000,
    );
});
onBeforeUnmount(() => {
    if (timer) clearInterval(timer);
});

function barColor(pct: number) {
    if (pct >= 90) return 'bg-red-500';
    if (pct >= 75) return 'bg-amber-500';
    return 'bg-emerald-500';
}

function barTextColor(pct: number) {
    if (pct >= 90) return 'text-red-600 dark:text-red-400';
    if (pct >= 75) return 'text-amber-600 dark:text-amber-400';
    return 'text-emerald-600 dark:text-emerald-400';
}

function metricWidth(pct: number) {
    return `${Math.min(100, Math.max(0, pct)).toFixed(1)}%`;
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="ops-page">
        <!-- No server configured -->
        <div
            v-if="!server"
            class="rounded-lg border border-dashed bg-card/80 p-10 text-center text-muted-foreground"
        >
            <Server class="mx-auto mb-3 size-10 opacity-50" />
            <p class="mb-2 font-medium">No server registered yet.</p>
            <Link href="/servers" class="text-primary underline"
                >Add a server</Link
            >
        </div>

        <template v-else>
            <section class="ops-hero">
                <div
                    class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge :variant="healthTone">
                                <Wifi v-if="reachable" class="size-3" />
                                <WifiOff v-else class="size-3" />
                                {{ healthLabel }}
                            </Badge>
                            <span class="text-xs text-muted-foreground">
                                refreshes every {{ refreshSeconds }}s
                            </span>
                        </div>
                        <h1 class="mt-3 text-2xl font-semibold tracking-tight">
                            {{ server.name }}
                        </h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ server.host }} ·
                            {{ metrics?.hostname ?? 'waiting for telemetry' }}
                        </p>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-4 xl:min-w-[44rem]">
                        <div class="ops-stat">
                            <div
                                class="flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                <Cpu class="size-4" />
                                CPU pressure
                            </div>
                            <p
                                class="mt-2 text-2xl font-semibold"
                                :class="barTextColor(cpuPressure)"
                            >
                                {{ cpuPressure.toFixed(0) }}%
                            </p>
                        </div>
                        <div class="ops-stat">
                            <div
                                class="flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                <MemoryStick class="size-4" />
                                Memory
                            </div>
                            <p
                                class="mt-2 text-2xl font-semibold"
                                :class="
                                    barTextColor(metrics?.memory.used_pct ?? 0)
                                "
                            >
                                {{ metrics?.memory.used_pct.toFixed(0) ?? 0 }}%
                            </p>
                        </div>
                        <div class="ops-stat">
                            <div
                                class="flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                <HardDrive class="size-4" />
                                Busiest disk
                            </div>
                            <p
                                class="mt-2 text-2xl font-semibold"
                                :class="
                                    barTextColor(busiestDisk?.used_pct ?? 0)
                                "
                            >
                                {{ busiestDisk?.used_pct.toFixed(0) ?? 0 }}%
                            </p>
                        </div>
                        <div class="ops-stat">
                            <div
                                class="flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                <Clock3 class="size-4" />
                                Uptime
                            </div>
                            <p class="mt-2 text-xl font-semibold">
                                {{ uptimeHuman }}
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

            <div
                v-if="!metrics && !error"
                class="rounded-lg border border-dashed bg-card/70 p-10 text-center text-sm text-muted-foreground"
            >
                Waiting for live system metrics.
            </div>

            <template v-if="metrics">
                <div class="grid gap-4 xl:grid-cols-[1.45fr_0.9fr]">
                    <Card class="overflow-hidden">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <TerminalSquare class="size-5 text-primary" />
                                Live System Monitor
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-5">
                            <div
                                class="rounded-md border bg-slate-950 p-4 font-mono text-xs text-slate-100 shadow-inner"
                            >
                                <div
                                    class="mb-3 flex flex-wrap items-center justify-between gap-2 text-slate-400"
                                >
                                    <span>htop-style telemetry</span>
                                    <span class="flex items-center gap-1">
                                        <RefreshCw
                                            class="size-3 animate-spin"
                                        />
                                        {{ metrics.time }}
                                    </span>
                                </div>
                                <div class="grid gap-3 lg:grid-cols-2">
                                    <div class="space-y-2">
                                        <div
                                            v-for="row in loadRows"
                                            :key="row.label"
                                            class="grid grid-cols-[3.5rem_1fr_4rem] items-center gap-2"
                                        >
                                            <span class="text-sky-300"
                                                >CPU {{ row.label }}</span
                                            >
                                            <div
                                                class="h-3 overflow-hidden rounded-sm bg-slate-800"
                                            >
                                                <div
                                                    class="h-full transition-all"
                                                    :class="barColor(row.pct)"
                                                    :style="{
                                                        width: metricWidth(
                                                            row.pct,
                                                        ),
                                                    }"
                                                />
                                            </div>
                                            <span
                                                class="text-right text-slate-300"
                                            >
                                                {{ row.value.toFixed(2) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <div
                                            class="grid grid-cols-[3.5rem_1fr_4rem] items-center gap-2"
                                        >
                                            <span class="text-emerald-300"
                                                >Mem</span
                                            >
                                            <div
                                                class="h-3 overflow-hidden rounded-sm bg-slate-800"
                                            >
                                                <div
                                                    class="h-full transition-all"
                                                    :class="
                                                        barColor(
                                                            metrics.memory
                                                                .used_pct,
                                                        )
                                                    "
                                                    :style="{
                                                        width: metricWidth(
                                                            metrics.memory
                                                                .used_pct,
                                                        ),
                                                    }"
                                                />
                                            </div>
                                            <span
                                                class="text-right text-slate-300"
                                            >
                                                {{
                                                    metrics.memory.used_pct.toFixed(
                                                        0,
                                                    )
                                                }}%
                                            </span>
                                        </div>
                                        <div
                                            class="grid grid-cols-[3.5rem_1fr_4rem] items-center gap-2"
                                        >
                                            <span class="text-violet-300"
                                                >Swap</span
                                            >
                                            <div
                                                class="h-3 overflow-hidden rounded-sm bg-slate-800"
                                            >
                                                <div
                                                    class="h-full transition-all"
                                                    :class="
                                                        barColor(swapUsedPct)
                                                    "
                                                    :style="{
                                                        width: metricWidth(
                                                            swapUsedPct,
                                                        ),
                                                    }"
                                                />
                                            </div>
                                            <span
                                                class="text-right text-slate-300"
                                            >
                                                {{ swapUsedPct.toFixed(0) }}%
                                            </span>
                                        </div>
                                        <div
                                            class="grid grid-cols-[3.5rem_1fr_4rem] items-center gap-2"
                                        >
                                            <span class="text-amber-300"
                                                >Disk</span
                                            >
                                            <div
                                                class="h-3 overflow-hidden rounded-sm bg-slate-800"
                                            >
                                                <div
                                                    class="h-full transition-all"
                                                    :class="
                                                        barColor(
                                                            busiestDisk?.used_pct ??
                                                                0,
                                                        )
                                                    "
                                                    :style="{
                                                        width: metricWidth(
                                                            busiestDisk?.used_pct ??
                                                                0,
                                                        ),
                                                    }"
                                                />
                                            </div>
                                            <span
                                                class="text-right text-slate-300"
                                            >
                                                {{
                                                    (
                                                        busiestDisk?.used_pct ??
                                                        0
                                                    ).toFixed(0)
                                                }}%
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    class="mt-4 grid gap-1 border-t border-slate-800 pt-3 text-slate-300 sm:grid-cols-2"
                                >
                                    <span
                                        v-for="line in terminalLines"
                                        :key="line"
                                        class="truncate"
                                    >
                                        <span class="text-slate-500">$</span>
                                        {{ line }}
                                    </span>
                                </div>
                            </div>

                            <div class="grid gap-3 md:grid-cols-3">
                                <div class="rounded-md border bg-muted/30 p-3">
                                    <p class="text-xs text-muted-foreground">
                                        CPU
                                    </p>
                                    <p class="mt-1 text-lg font-semibold">
                                        {{ metrics.cpu_count }} cores
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        load normalized to core count
                                    </p>
                                </div>
                                <div class="rounded-md border bg-muted/30 p-3">
                                    <p class="text-xs text-muted-foreground">
                                        Memory
                                    </p>
                                    <p class="mt-1 text-lg font-semibold">
                                        {{ memUsedGB }} / {{ memTotalGB }} GB
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ memAvailableGB }} GB available
                                    </p>
                                </div>
                                <div class="rounded-md border bg-muted/30 p-3">
                                    <p class="text-xs text-muted-foreground">
                                        Platform
                                    </p>
                                    <p
                                        class="mt-1 truncate text-lg font-semibold"
                                    >
                                        {{ metrics.os }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ metrics.arch }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Activity class="size-5 text-primary" />
                                Pressure Summary
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-3">
                                <div
                                    v-for="row in loadRows"
                                    :key="`summary-${row.label}`"
                                >
                                    <div
                                        class="mb-1 flex justify-between text-sm"
                                    >
                                        <span>Load {{ row.label }}</span>
                                        <span :class="barTextColor(row.pct)"
                                            >{{ row.pct.toFixed(0) }}%</span
                                        >
                                    </div>
                                    <div
                                        class="h-2 overflow-hidden rounded bg-muted"
                                    >
                                        <div
                                            class="h-full transition-all"
                                            :class="barColor(row.pct)"
                                            :style="{
                                                width: metricWidth(row.pct),
                                            }"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-md border bg-muted/30 p-3">
                                <div class="flex items-start gap-2">
                                    <AlertTriangle
                                        class="mt-0.5 size-4"
                                        :class="
                                            diskWarnings
                                                ? 'text-amber-500'
                                                : 'text-emerald-500'
                                        "
                                    />
                                    <div>
                                        <p class="text-sm font-medium">
                                            Disk watch
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                diskWarnings
                                                    ? `${diskWarnings} mount(s) above 80%`
                                                    : 'All mounts below 80%'
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card v-if="metrics.disk?.length">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Database class="size-5 text-primary" />
                            Disk Usage
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-3 lg:grid-cols-2">
                            <div
                                v-for="d in metrics.disk"
                                :key="d.mount"
                                class="rounded-md border bg-muted/20 p-3"
                            >
                                <div
                                    class="mb-2 flex items-center justify-between gap-3 text-sm"
                                >
                                    <span class="truncate font-mono">{{
                                        d.mount
                                    }}</span>
                                    <span
                                        class="shrink-0 font-medium"
                                        :class="barTextColor(d.used_pct)"
                                    >
                                        {{ d.used_pct.toFixed(1) }}%
                                    </span>
                                </div>
                                <div
                                    class="h-2 overflow-hidden rounded bg-muted"
                                >
                                    <div
                                        class="h-full transition-all"
                                        :class="barColor(d.used_pct)"
                                        :style="{
                                            width: metricWidth(d.used_pct),
                                        }"
                                    />
                                </div>
                                <p class="mt-2 text-xs text-muted-foreground">
                                    {{ d.used_gb.toFixed(1) }} /
                                    {{ d.total_gb.toFixed(1) }} GB used ·
                                    {{ d.free_gb.toFixed(1) }} GB free
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <TerminalSquare class="size-5 text-primary" />
                            Recent Agent Activity
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="!recent_logs.length"
                            class="py-8 text-center text-sm text-muted-foreground"
                        >
                            No activity yet.
                        </div>
                        <div v-else class="overflow-hidden rounded-md border">
                            <table class="w-full text-sm">
                                <thead
                                    class="bg-muted/60 text-left text-xs text-muted-foreground"
                                >
                                    <tr>
                                        <th class="px-3 py-2">Time</th>
                                        <th class="px-3 py-2">Action</th>
                                        <th class="px-3 py-2">Server</th>
                                        <th class="px-3 py-2 text-right">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="log in recent_logs"
                                        :key="log.id"
                                        class="border-t"
                                    >
                                        <td
                                            class="px-3 py-2 text-xs text-muted-foreground"
                                        >
                                            {{ log.created_at }}
                                        </td>
                                        <td class="px-3 py-2 font-mono">
                                            {{ log.action }}
                                        </td>
                                        <td
                                            class="px-3 py-2 text-muted-foreground"
                                        >
                                            {{
                                                log.server?.name ??
                                                'unknown server'
                                            }}
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <Badge
                                                :variant="
                                                    log.status === 'ok'
                                                        ? 'default'
                                                        : 'destructive'
                                                "
                                            >
                                                {{ log.status }}
                                            </Badge>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </template>
        </template>
    </div>
</template>
