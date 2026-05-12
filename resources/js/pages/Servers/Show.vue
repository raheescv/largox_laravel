<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Globe, Server, RefreshCw, Trash2 } from 'lucide-vue-next';
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
import ServerController from '@/actions/App/Http/Controllers/ServerController';
import serverRoutes from '@/routes/servers';
import serverSiteRoutes from '@/routes/servers/sites';
import { dashboard } from '@/routes';

type SiteModel = {
    id: number;
    domain: string;
    path: string;
    branch: string;
};

type ServerModel = {
    id: number;
    name: string;
    host: string;
    port: number;
    scheme: string;
    status: string;
    ssh_user: string;
    ssh_port: number;
    sites: SiteModel[];
};

type Health = {
    reachable: boolean;
    body?: Record<string, unknown>;
    error?: string;
};

defineOptions({
    layout: (pageProps: { server: ServerModel }) => ({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Servers', href: serverRoutes.index() },
            { title: pageProps.server.name, href: serverRoutes.show(pageProps.server) },
        ],
    }),
});

const props = defineProps<{ server: ServerModel; health: Health }>();

const updateForm = useForm({
    name: props.server.name,
    host: props.server.host,
    port: props.server.port,
    scheme: props.server.scheme,
    agent_secret: '',
    ssh_user: props.server.ssh_user,
    ssh_port: props.server.ssh_port,
});

function submitUpdate() {
    updateForm.patch(ServerController.update.url(props.server));
}

const deleteForm = useForm({});
function submitDelete() {
    if (confirm(`Delete server "${props.server.name}"?`)) {
        deleteForm.delete(ServerController.destroy.url(props.server));
    }
}

const dispatchForm = useForm({ action: '', payload: {} });
function dispatch(action: string) {
    dispatchForm.action = action;
    dispatchForm.post(ServerController.dispatchAction.url(props.server));
}
</script>

<template>
    <Head :title="server.name" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <Heading :title="server.name" :description="`${server.scheme}://${server.host}:${server.port}`" />
            <Button variant="destructive" size="sm" :disabled="deleteForm.processing" @click="submitDelete">
                <Trash2 class="mr-2 size-4" />
                Delete
            </Button>
        </div>

        <!-- Health -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    Agent Health
                    <Badge :variant="health.reachable ? 'default' : 'destructive'">
                        {{ health.reachable ? 'online' : 'unreachable' }}
                    </Badge>
                </CardTitle>
            </CardHeader>
            <CardContent>
                <pre v-if="health.body" class="text-xs bg-muted rounded p-3 overflow-x-auto">{{ JSON.stringify(health.body, null, 2) }}</pre>
                <p v-else-if="health.error" class="text-sm text-destructive">{{ health.error }}</p>
            </CardContent>
        </Card>

        <!-- Quick actions -->
        <Card>
            <CardHeader>
                <CardTitle>Quick Actions</CardTitle>
                <CardDescription>Send commands to the Go agent</CardDescription>
            </CardHeader>
            <CardContent class="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" :disabled="dispatchForm.processing" @click="dispatch('nginx_test')">
                    <RefreshCw class="mr-2 size-4" /> nginx test
                </Button>
                <Button variant="outline" size="sm" :disabled="dispatchForm.processing" @click="dispatch('nginx_reload')">
                    <RefreshCw class="mr-2 size-4" /> nginx reload
                </Button>
                <Button variant="outline" size="sm" :disabled="dispatchForm.processing" @click="dispatch('nginx_restart')">
                    <RefreshCw class="mr-2 size-4" /> nginx restart
                </Button>
            </CardContent>
        </Card>

        <!-- Sites -->
        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <div>
                    <CardTitle>Sites</CardTitle>
                    <CardDescription>Hosted on this server</CardDescription>
                </div>
                <Link :href="serverSiteRoutes.index(server)">
                    <Button variant="outline" size="sm">
                        <Globe class="mr-2 size-4" /> View all
                    </Button>
                </Link>
            </CardHeader>
            <CardContent>
                <div v-if="server.sites.length === 0" class="text-center text-sm text-muted-foreground py-6">
                    No sites yet.
                </div>
                <div v-else class="divide-y">
                    <div
                        v-for="site in server.sites"
                        :key="site.id"
                        class="flex items-center justify-between py-3"
                    >
                        <div>
                            <p class="font-medium text-sm">{{ site.domain }}</p>
                            <p class="text-xs text-muted-foreground">{{ site.path }}</p>
                        </div>
                        <Badge variant="secondary">{{ site.branch }}</Badge>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Update form -->
        <Card>
            <CardHeader>
                <CardTitle>Settings</CardTitle>
                <CardDescription>Update server connection details</CardDescription>
            </CardHeader>
            <CardContent>
                <form class="space-y-4" @submit.prevent="submitUpdate">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="updateForm.name" required />
                        <p v-if="updateForm.errors.name" class="text-sm text-destructive">{{ updateForm.errors.name }}</p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="host">Host / IP</Label>
                        <Input id="host" v-model="updateForm.host" required />
                        <p v-if="updateForm.errors.host" class="text-sm text-destructive">{{ updateForm.errors.host }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="port">Port</Label>
                            <Input id="port" v-model="updateForm.port" type="number" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="scheme">Scheme</Label>
                            <Input id="scheme" v-model="updateForm.scheme" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="agent_secret">New Agent Secret <span class="text-muted-foreground">(leave blank to keep)</span></Label>
                        <Input id="agent_secret" v-model="updateForm.agent_secret" type="password" />
                        <p v-if="updateForm.errors.agent_secret" class="text-sm text-destructive">{{ updateForm.errors.agent_secret }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="ssh_user">SSH User</Label>
                            <Input id="ssh_user" v-model="updateForm.ssh_user" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="ssh_port">SSH Port</Label>
                            <Input id="ssh_port" v-model="updateForm.ssh_port" type="number" />
                        </div>
                    </div>
                    <Button type="submit" :disabled="updateForm.processing">
                        <Server class="mr-2 size-4" /> Save
                    </Button>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
