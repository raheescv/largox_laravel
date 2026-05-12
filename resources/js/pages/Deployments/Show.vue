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
import deploymentRoutes from '@/routes/deployments';
import siteRoutes from '@/routes/sites';
import serverRoutes from '@/routes/servers';
import serverSiteRoutes from '@/routes/servers/sites';
import { dashboard } from '@/routes';

type UserModel = { id: number; name: string } | null;
type ServerModel = { id: number; name: string };
type SiteModel = { id: number; domain: string; server: ServerModel };
type DeploymentModel = {
    id: number;
    status: string;
    branch: string | null;
    commit_sha: string | null;
    output: string | null;
    error: string | null;
    created_at: string;
    started_at: string | null;
    finished_at: string | null;
    site: SiteModel;
    user: UserModel;
};

defineOptions({
    layout: (pageProps: { deployment: DeploymentModel }) => ({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Servers', href: serverRoutes.index() },
            { title: pageProps.deployment.site.server.name, href: serverRoutes.show(pageProps.deployment.site.server) },
            { title: 'Sites', href: serverSiteRoutes.index(pageProps.deployment.site.server) },
            { title: pageProps.deployment.site.domain, href: siteRoutes.show(pageProps.deployment.site) },
            { title: `Deployment #${pageProps.deployment.id}`, href: deploymentRoutes.show(pageProps.deployment) },
        ],
    }),
});

const props = defineProps<{ deployment: DeploymentModel }>();

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'success') return 'default';
    if (status === 'failed') return 'destructive';
    if (status === 'running') return 'outline';
    return 'secondary';
}
</script>

<template>
    <Head :title="`Deployment #${deployment.id}`" />

    <div class="space-y-6">
        <div class="flex items-center gap-3">
            <Heading :title="`Deployment #${deployment.id}`" :description="deployment.site.domain" />
            <Badge :variant="statusVariant(deployment.status)">{{ deployment.status }}</Badge>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Details</CardTitle>
            </CardHeader>
            <CardContent>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-muted-foreground">Branch</dt>
                        <dd>{{ deployment.branch ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Commit</dt>
                        <dd class="font-mono text-xs">{{ deployment.commit_sha ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Triggered by</dt>
                        <dd>{{ deployment.user?.name ?? 'system' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Started</dt>
                        <dd>{{ deployment.started_at ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Finished</dt>
                        <dd>{{ deployment.finished_at ?? '—' }}</dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        <Card v-if="deployment.output">
            <CardHeader>
                <CardTitle>Output</CardTitle>
            </CardHeader>
            <CardContent>
                <pre class="text-xs bg-muted rounded p-4 overflow-x-auto whitespace-pre-wrap">{{ deployment.output }}</pre>
            </CardContent>
        </Card>

        <Card v-if="deployment.error">
            <CardHeader>
                <CardTitle class="text-destructive">Error</CardTitle>
            </CardHeader>
            <CardContent>
                <pre class="text-xs text-destructive bg-destructive/10 rounded p-4 overflow-x-auto whitespace-pre-wrap">{{ deployment.error }}</pre>
            </CardContent>
        </Card>
    </div>
</template>
