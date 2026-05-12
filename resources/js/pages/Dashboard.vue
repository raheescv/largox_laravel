<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted } from 'vue';
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

const uptimeHuman = computed(() => {
    const s = props.metrics?.uptime_sec ?? 0;
    const d = Math.floor(s / 86400);
    const h = Math.floor((s % 86400) / 3600);
    const m = Math.floor((s % 3600) / 60);
    return `${d}d ${h}h ${m}m`;
});

const memUsedGB = computed(() =>
    props.metrics ? (props.metrics.memory.used_kb / 1024 / 1024).toFixed(2) : '0',
);
const memTotalGB = computed(() =>
    props.metrics ? (props.metrics.memory.total_kb / 1024 / 1024).toFixed(2) : '0',
);

let timer: ReturnType<typeof setInterval> | null = null;
onMounted(() => {
    timer = setInterval(() => router.reload({ only: ['metrics', 'health'] }), 10000);
});
onBeforeUnmount(() => {
    if (timer) clearInterval(timer);
});

function barColor(pct: number) {
    if (pct >= 90) return 'bg-red-500';
    if (pct >= 75) return 'bg-amber-500';
    return 'bg-emerald-500';
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <!-- No server configured -->
        <div
            v-if="!server"
            class="rounded-xl border border-dashed p-8 text-center text-muted-foreground"
        >
            <p class="mb-2 font-medium">No server registered yet.</p>
            <Link href="/servers" class="text-primary underline">Add a server</Link>
        </div>

        <template v-else>
            <!-- Header strip -->
            <div class="flex items-center justify-between rounded-xl border p-4">
                <div>
                    <div class="text-sm text-muted-foreground">Server</div>
                    <div class="text-lg font-semibold">{{ server.name }} <span class="text-muted-foreground">· {{ server.host }}</span></div>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        class="inline-flex h-2 w-2 rounded-full"
                        :class="reachable ? 'bg-emerald-500' : 'bg-red-500'"
                    />
                    <span class="text-sm">{{ reachable ? 'Agent online' : 'Agent unreachable' }}</span>
                </div>
            </div>

            <div v-if="error" class="rounded-xl border border-red-300 bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40">
                {{ error }}
            </div>

            <!-- Metric cards -->
            <div v-if="metrics" class="grid gap-4 md:grid-cols-3">
                <!-- CPU / Load -->
                <div class="rounded-xl border p-4">
                    <div class="text-sm text-muted-foreground">CPU load (1m / 5m / 15m)</div>
                    <div class="mt-1 text-2xl font-semibold">
                        {{ metrics.load_avg[0].toFixed(2) }}
                        <span class="text-muted-foreground text-base font-normal">
                            / {{ metrics.load_avg[1].toFixed(2) }} / {{ metrics.load_avg[2].toFixed(2) }}
                        </span>
                    </div>
                    <div class="mt-2 text-xs text-muted-foreground">{{ metrics.cpu_count }} cores · {{ metrics.os }}/{{ metrics.arch }}</div>
                </div>

                <!-- Memory -->
                <div class="rounded-xl border p-4">
                    <div class="text-sm text-muted-foreground">Memory</div>
                    <div class="mt-1 text-2xl font-semibold">
                        {{ memUsedGB }} <span class="text-base text-muted-foreground">/ {{ memTotalGB }} GB</span>
                    </div>
                    <div class="mt-3 h-2 w-full overflow-hidden rounded bg-muted">
                        <div
                            class="h-full transition-all"
                            :class="barColor(metrics.memory.used_pct)"
                            :style="{ width: metrics.memory.used_pct.toFixed(1) + '%' }"
                        />
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">{{ metrics.memory.used_pct.toFixed(1) }}% used</div>
                </div>

                <!-- Uptime -->
                <div class="rounded-xl border p-4">
                    <div class="text-sm text-muted-foreground">Uptime</div>
                    <div class="mt-1 text-2xl font-semibold">{{ uptimeHuman }}</div>
                    <div class="mt-2 text-xs text-muted-foreground">{{ metrics.hostname }}</div>
                </div>
            </div>

            <!-- Disk -->
            <div v-if="metrics?.disk?.length" class="rounded-xl border p-4">
                <div class="mb-3 text-sm font-medium">Disk usage</div>
                <div class="space-y-3">
                    <div v-for="d in metrics.disk" :key="d.mount">
                        <div class="flex justify-between text-sm">
                            <span class="font-mono">{{ d.mount }}</span>
                            <span class="text-muted-foreground">
                                {{ d.used_gb.toFixed(1) }} / {{ d.total_gb.toFixed(1) }} GB ({{ d.used_pct.toFixed(1) }}%)
                            </span>
                        </div>
                        <div class="mt-1 h-2 w-full overflow-hidden rounded bg-muted">
                            <div
                                class="h-full transition-all"
                                :class="barColor(d.used_pct)"
                                :style="{ width: Math.min(100, d.used_pct).toFixed(1) + '%' }"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent audit log -->
            <div class="rounded-xl border p-4">
                <div class="mb-3 text-sm font-medium">Recent activity</div>
                <div v-if="!recent_logs.length" class="text-sm text-muted-foreground">No activity yet.</div>
                <table v-else class="w-full text-sm">
                    <thead class="text-left text-muted-foreground">
                        <tr>
                            <th class="py-1">When</th>
                            <th class="py-1">Action</th>
                            <th class="py-1">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in recent_logs" :key="log.id" class="border-t">
                            <td class="py-1">{{ log.created_at }}</td>
                            <td class="py-1 font-mono">{{ log.action }}</td>
                            <td class="py-1">
                                <span
                                    class="rounded px-2 py-0.5 text-xs"
                                    :class="log.status === 'ok' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                                >
                                    {{ log.status }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>
