<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import deploymentRoutes from '@/routes/deployments';
import siteRoutes from '@/routes/sites';
import serverRoutes from '@/routes/servers';
import serverSiteRoutes from '@/routes/servers/sites';
import { dashboard } from '@/routes';

type SiteModel = { id: number; domain: string; server: { id: number; name: string } };
type DeploymentModel = {
    id: number;
    status: string;
    branch: string | null;
    commit_sha: string | null;
    created_at: string;
    started_at: string | null;
    finished_at: string | null;
};
type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
};

defineOptions({
    layout: (pageProps: { site: SiteModel }) => ({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Servers', href: serverRoutes.index() },
            { title: pageProps.site.server.name, href: serverRoutes.show(pageProps.site.server) },
            { title: 'Sites', href: serverSiteRoutes.index(pageProps.site.server) },
            { title: pageProps.site.domain, href: siteRoutes.show(pageProps.site) },
            { title: 'Deployments', href: deploymentRoutes.index(pageProps.site) },
        ],
    }),
});

const props = defineProps<{ site: SiteModel; deployments: Paginated<DeploymentModel> }>();

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'success') return 'default';
    if (status === 'failed') return 'destructive';
    if (status === 'running') return 'outline';
    return 'secondary';
}
</script>

<template>
    <Head :title="`Deployments — ${site.domain}`" />

    <div class="space-y-6">
        <Heading :title="`Deployments — ${site.domain}`" />

        <Card>
            <CardHeader>
                <CardTitle>All Deployments</CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="deployments.data.length === 0" class="text-center text-sm text-muted-foreground py-6">
                    No deployments yet.
                </div>
                <div v-else class="divide-y">
                    <Link
                        v-for="d in deployments.data"
                        :key="d.id"
                        :href="deploymentRoutes.show(d)"
                        class="flex items-center justify-between py-3 hover:bg-accent/40 px-2 rounded"
                    >
                        <div>
                            <p class="text-sm font-medium">#{{ d.id }} — {{ d.branch ?? '—' }}</p>
                            <p class="text-xs text-muted-foreground">{{ d.commit_sha ?? '' }} {{ d.created_at }}</p>
                        </div>
                        <Badge :variant="statusVariant(d.status)">{{ d.status }}</Badge>
                    </Link>
                </div>

                <div v-if="deployments.last_page > 1" class="flex justify-between mt-4">
                    <a
                        v-if="deployments.prev_page_url"
                        :href="deployments.prev_page_url"
                        class="text-sm text-primary hover:underline"
                    >← Previous</a>
                    <span class="text-sm text-muted-foreground mx-auto">
                        Page {{ deployments.current_page }} of {{ deployments.last_page }}
                    </span>
                    <a
                        v-if="deployments.next_page_url"
                        :href="deployments.next_page_url"
                        class="text-sm text-primary hover:underline"
                    >Next →</a>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
