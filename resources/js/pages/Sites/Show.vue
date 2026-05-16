<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { GitBranch, LayoutDashboard, Play, Trash2 } from 'lucide-vue-next';
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
import { Checkbox } from '@/components/ui/checkbox';
import SiteController from '@/actions/App/Http/Controllers/SiteController';
import DeploymentController from '@/actions/App/Http/Controllers/DeploymentController';
import siteRoutes from '@/routes/sites';
import serverRoutes from '@/routes/servers';
import serverSiteRoutes from '@/routes/servers/sites';
import { dashboard } from '@/routes';

type ServerModel = { id: number; name: string };
type DeploymentModel = {
    id: number;
    status: string;
    branch: string | null;
    created_at: string;
    started_at: string | null;
    finished_at: string | null;
};
type SiteModel = {
    id: number;
    domain: string;
    path: string;
    branch: string;
    repository: string | null;
    composer: boolean;
    npm_build: boolean;
    php_service: string;
    queue_program: string | null;
    artisan_cmds: string[];
    server: ServerModel;
};

defineOptions({
    layout: (pageProps: { site: SiteModel }) => ({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Servers', href: serverRoutes.index() },
            { title: pageProps.site.server.name, href: serverRoutes.show(pageProps.site.server) },
            { title: 'Sites', href: serverSiteRoutes.index(pageProps.site.server) },
            { title: pageProps.site.domain, href: siteRoutes.show(pageProps.site) },
        ],
    }),
});

const props = defineProps<{ site: SiteModel; deployments: DeploymentModel[] }>();

const updateForm = useForm({
    server_id: props.site.server.id,
    domain: props.site.domain,
    path: props.site.path,
    repository: props.site.repository ?? '',
    branch: props.site.branch,
    composer: props.site.composer,
    npm_build: props.site.npm_build,
    artisan_cmds: props.site.artisan_cmds ?? [],
    php_service: props.site.php_service,
    queue_program: props.site.queue_program ?? '',
});

function submitUpdate() {
    updateForm.patch(SiteController.update.url(props.site));
}

const deleteForm = useForm({});
function submitDelete() {
    if (confirm(`Delete site "${props.site.domain}"?`)) {
        deleteForm.delete(SiteController.destroy.url(props.site));
    }
}

const deployForm = useForm({ branch: props.site.branch });
function deploy() {
    deployForm.post(DeploymentController.store.url(props.site));
}

const cloneForm = useForm({ repository: props.site.repository ?? '', branch: props.site.branch });
function clone() {
    cloneForm.post(SiteController.clone.url(props.site));
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'success') return 'default';
    if (status === 'failed') return 'destructive';
    if (status === 'running') return 'outline';
    return 'secondary';
}
</script>

<template>
    <Head :title="site.domain" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <Heading :title="site.domain" :description="site.path" />
            <div class="flex gap-2">
                <Button variant="outline" size="sm" :href="`/sites/${site.id}/deploy`" as="a">
                    <LayoutDashboard class="mr-2 size-4" /> Deploy Dashboard
                </Button>
                <Button variant="destructive" size="sm" :disabled="deleteForm.processing" @click="submitDelete">
                    <Trash2 class="mr-2 size-4" /> Delete
                </Button>
            </div>
        </div>

        <!-- Deploy -->
        <Card>
            <CardHeader>
                <CardTitle>Deploy</CardTitle>
                <CardDescription>Trigger a deployment for this site</CardDescription>
            </CardHeader>
            <CardContent class="flex items-end gap-4">
                <div class="grid gap-2 flex-1">
                    <Label for="deploy_branch">Branch</Label>
                    <Input id="deploy_branch" v-model="deployForm.branch" placeholder="main" />
                </div>
                <Button :disabled="deployForm.processing" @click="deploy">
                    <Play class="mr-2 size-4" /> Deploy
                </Button>
                <Button variant="outline" :disabled="cloneForm.processing" @click="clone">
                    <GitBranch class="mr-2 size-4" /> Clone repo
                </Button>
            </CardContent>
        </Card>

        <!-- Recent deployments -->
        <Card>
            <CardHeader>
                <CardTitle>Recent Deployments</CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="deployments.length === 0" class="text-center text-sm text-muted-foreground py-6">
                    No deployments yet.
                </div>
                <div v-else class="divide-y">
                    <div
                        v-for="d in deployments"
                        :key="d.id"
                        class="flex items-center justify-between py-3"
                    >
                        <div>
                            <p class="text-sm font-medium">#{{ d.id }} — {{ d.branch ?? site.branch }}</p>
                            <p class="text-xs text-muted-foreground">{{ d.created_at }}</p>
                        </div>
                        <Badge :variant="statusVariant(d.status)">{{ d.status }}</Badge>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Settings -->
        <Card>
            <CardHeader>
                <CardTitle>Settings</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="space-y-4" @submit.prevent="submitUpdate">
                    <div class="grid gap-2">
                        <Label for="domain">Domain</Label>
                        <Input id="domain" v-model="updateForm.domain" required />
                        <p v-if="updateForm.errors.domain" class="text-sm text-destructive">{{ updateForm.errors.domain }}</p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="path">Path</Label>
                        <Input id="path" v-model="updateForm.path" required />
                        <p v-if="updateForm.errors.path" class="text-sm text-destructive">{{ updateForm.errors.path }}</p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="repository">Repository</Label>
                        <Input id="repository" v-model="updateForm.repository" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="branch">Branch</Label>
                            <Input id="branch" v-model="updateForm.branch" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="php_service">PHP Service</Label>
                            <Input id="php_service" v-model="updateForm.php_service" />
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox id="composer" v-model:checked="updateForm.composer" />
                            Composer install
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox id="npm_build" v-model:checked="updateForm.npm_build" />
                            npm build
                        </label>
                    </div>
                    <div class="grid gap-2">
                        <Label for="queue_program">Supervisor queue program</Label>
                        <Input id="queue_program" v-model="updateForm.queue_program" placeholder="laravel-worker" />
                    </div>
                    <Button type="submit" :disabled="updateForm.processing">Save</Button>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
