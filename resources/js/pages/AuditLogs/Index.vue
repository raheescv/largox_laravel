<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import auditRoutes from '@/routes/audit';

type LogModel = {
    id: number;
    action: string;
    status: string;
    ip: string | null;
    created_at: string;
    user: { id: number; name: string } | null;
    server: { id: number; name: string } | null;
};
type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
};

defineProps<{ logs: Paginated<LogModel> }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Audit Logs', href: auditRoutes.index() },
        ],
    },
});
</script>

<template>
    <Head title="Audit Logs" />

    <div class="space-y-6">
        <Heading title="Audit Logs" description="All agent actions and their outcomes" />

        <Card>
            <CardHeader>
                <CardTitle>Log entries</CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="logs.data.length === 0" class="text-center text-sm text-muted-foreground py-6">
                    No log entries yet.
                </div>
                <div v-else class="divide-y text-sm">
                    <div
                        v-for="log in logs.data"
                        :key="log.id"
                        class="flex items-start justify-between py-3 gap-4"
                    >
                        <div class="min-w-0">
                            <p class="font-medium truncate">{{ log.action }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ log.server?.name ?? 'unknown server' }}
                                · {{ log.user?.name ?? 'system' }}
                                · {{ log.ip ?? '' }}
                                · {{ log.created_at }}
                            </p>
                        </div>
                        <Badge :variant="log.status === 'ok' ? 'default' : 'destructive'" class="shrink-0">
                            {{ log.status }}
                        </Badge>
                    </div>
                </div>

                <div v-if="logs.last_page > 1" class="flex justify-between mt-4">
                    <a
                        v-if="logs.prev_page_url"
                        :href="logs.prev_page_url"
                        class="text-sm text-primary hover:underline"
                    >← Previous</a>
                    <span class="text-sm text-muted-foreground mx-auto">
                        Page {{ logs.current_page }} of {{ logs.last_page }}
                    </span>
                    <a
                        v-if="logs.next_page_url"
                        :href="logs.next_page_url"
                        class="text-sm text-primary hover:underline"
                    >Next →</a>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
