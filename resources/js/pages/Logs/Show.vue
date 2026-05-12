<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { RefreshCw } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import logRoutes from '@/routes/logs';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Log Viewer', href: logRoutes.show() },
        ],
    },
});

const props = defineProps<{
    path: string;
    lines: string[];
    error: string | null;
}>();

const pathInput = ref(props.path);
const linesInput = ref('200');

function load() {
    router.get(logRoutes.show.url({ query: { path: pathInput.value, lines: linesInput.value } }), {}, { preserveState: false });
}

function refresh() {
    router.reload();
}

const commonPaths = [
    '/var/log/nginx/error.log',
    '/var/log/nginx/access.log',
    '/var/log/syslog',
    '/var/log/auth.log',
];
</script>

<template>
    <Head title="Log Viewer" />

    <div class="space-y-6">
        <Heading title="Log Viewer" description="Tail log files from the server" />

        <Card>
            <CardHeader>
                <CardTitle>Log file</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="grid gap-2 flex-1 min-w-48">
                        <Label for="logpath">Path</Label>
                        <Input id="logpath" v-model="pathInput" placeholder="/var/log/nginx/error.log" @keydown.enter.prevent="load" />
                    </div>
                    <div class="grid gap-2 w-24">
                        <Label for="loglines">Lines</Label>
                        <Input id="loglines" v-model="linesInput" type="number" min="10" max="2000" @keydown.enter.prevent="load" />
                    </div>
                    <Button @click="load">Load</Button>
                    <Button v-if="path" variant="outline" @click="refresh">
                        <RefreshCw class="mr-2 size-4" /> Refresh
                    </Button>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="p in commonPaths"
                        :key="p"
                        type="button"
                        class="text-xs rounded-full border px-3 py-1 hover:bg-muted transition-colors"
                        :class="{ 'bg-muted': pathInput === p }"
                        @click="pathInput = p; load()"
                    >
                        {{ p.split('/').pop() }}
                    </button>
                </div>
            </CardContent>
        </Card>

        <div v-if="error" class="rounded-xl border border-red-300 bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40">
            {{ error }}
        </div>

        <Card v-if="lines.length > 0">
            <CardHeader>
                <CardTitle class="flex items-center justify-between">
                    <span class="font-mono text-sm font-normal text-muted-foreground">{{ path }}</span>
                    <span class="text-xs text-muted-foreground">{{ lines.length }} lines</span>
                </CardTitle>
            </CardHeader>
            <CardContent>
                <pre class="overflow-x-auto text-xs font-mono bg-muted/40 rounded-md p-4 max-h-[70vh] overflow-y-auto">{{ lines.join('\n') }}</pre>
            </CardContent>
        </Card>

        <Card v-else-if="path && !error">
            <CardContent class="py-8 text-center text-sm text-muted-foreground">
                No log lines found.
            </CardContent>
        </Card>
    </div>
</template>
