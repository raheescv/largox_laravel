<script setup lang="ts">
import { Head, Form, Link } from '@inertiajs/vue3';
import { PlusCircle, Server } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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
import { dashboard } from '@/routes';

type ServerModel = {
    id: number;
    name: string;
    host: string;
    port: number;
    scheme: string;
    status: string;
    created_at: string;
};

defineProps<{ servers: ServerModel[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Servers', href: serverRoutes.index() },
        ],
    },
});

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'online') return 'default';
    if (status === 'offline') return 'destructive';
    return 'secondary';
}
</script>

<template>
    <Head title="Servers" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <Heading title="Servers" description="Manage your connected servers" />
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Add Server</CardTitle>
                    <CardDescription>Connect a new server running the Go agent</CardDescription>
                </CardHeader>
                <CardContent>
                    <Form
                        v-bind="ServerController.store.form()"
                        class="space-y-4"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label for="name">Name</Label>
                            <Input id="name" name="name" placeholder="Production" required />
                            <InputError :message="errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="host">Host / IP</Label>
                            <Input id="host" name="host" placeholder="127.0.0.1" required />
                            <InputError :message="errors.host" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="port">Port</Label>
                                <Input id="port" name="port" type="number" default-value="8088" />
                                <InputError :message="errors.port" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="scheme">Scheme</Label>
                                <Input id="scheme" name="scheme" default-value="http" />
                                <InputError :message="errors.scheme" />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label for="agent_secret">Agent Secret</Label>
                            <Input id="agent_secret" name="agent_secret" type="password" placeholder="Min 32 characters" required />
                            <InputError :message="errors.agent_secret" />
                        </div>
                        <Button type="submit" :disabled="processing" class="w-full">
                            <PlusCircle class="mr-2 size-4" />
                            Add Server
                        </Button>
                    </Form>
                </CardContent>
            </Card>

            <div class="space-y-4">
                <div v-if="servers.length === 0" class="flex flex-col items-center justify-center rounded-xl border border-dashed py-12 text-center text-muted-foreground">
                    <Server class="mb-3 size-10 opacity-40" />
                    <p class="text-sm">No servers yet. Add one to get started.</p>
                </div>
                <Link
                    v-for="server in servers"
                    :key="server.id"
                    :href="serverRoutes.show(server)"
                    class="block"
                >
                    <Card class="transition-colors hover:bg-accent/50">
                        <CardContent class="flex items-center justify-between py-4">
                            <div class="flex items-center gap-3">
                                <Server class="size-5 text-muted-foreground" />
                                <div>
                                    <p class="font-medium">{{ server.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ server.scheme }}://{{ server.host }}:{{ server.port }}</p>
                                </div>
                            </div>
                            <Badge :variant="statusVariant(server.status)">{{ server.status }}</Badge>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </div>
    </div>
</template>
