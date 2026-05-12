<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Globe, PlusCircle } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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
import siteRoutes from '@/routes/sites';
import serverRoutes from '@/routes/servers';
import serverSiteRoutes from '@/routes/servers/sites';
import { dashboard } from '@/routes';

type ServerModel = { id: number; name: string };
type SiteModel = {
    id: number;
    domain: string;
    path: string;
    branch: string;
    repository: string | null;
    composer: boolean;
    npm_build: boolean;
};

defineOptions({
    layout: (pageProps: { server: ServerModel }) => ({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Servers', href: serverRoutes.index() },
            { title: pageProps.server.name, href: serverRoutes.show(pageProps.server) },
            { title: 'Sites', href: serverSiteRoutes.index(pageProps.server) },
        ],
    }),
});

const props = defineProps<{ server: ServerModel; sites: SiteModel[] }>();

const form = useForm({
    server_id: props.server.id,
    domain: '',
    path: '',
    repository: '',
    branch: 'main',
    composer: true,
    npm_build: false,
    artisan_cmds: [] as string[],
    php_service: 'php8.3-fpm',
    queue_program: '',
});

function submit() {
    form.post(SiteController.store.url());
}
</script>

<template>
    <Head title="Sites" />

    <div class="space-y-6">
        <Heading :title="`Sites — ${server.name}`" description="Manage sites hosted on this server" />

        <div class="grid gap-6 md:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Add Site</CardTitle>
                    <CardDescription>Register a new site on this server</CardDescription>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="grid gap-2">
                            <Label for="domain">Domain</Label>
                            <Input id="domain" v-model="form.domain" placeholder="example.com" required />
                            <InputError :message="form.errors.domain" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="path">Path</Label>
                            <Input id="path" v-model="form.path" placeholder="/var/www/example.com" required />
                            <InputError :message="form.errors.path" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="repository">Repository <span class="text-muted-foreground">(optional)</span></Label>
                            <Input id="repository" v-model="form.repository" placeholder="git@github.com:org/repo.git" />
                            <InputError :message="form.errors.repository" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="branch">Branch</Label>
                            <Input id="branch" v-model="form.branch" placeholder="main" />
                        </div>
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-2 text-sm">
                                <Checkbox id="composer" v-model:checked="form.composer" />
                                Composer install
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <Checkbox id="npm_build" v-model:checked="form.npm_build" />
                                npm build
                            </label>
                        </div>
                        <Button type="submit" :disabled="form.processing" class="w-full">
                            <PlusCircle class="mr-2 size-4" /> Add Site
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <div class="space-y-4">
                <div v-if="sites.length === 0" class="flex flex-col items-center justify-center rounded-xl border border-dashed py-12 text-center text-muted-foreground">
                    <Globe class="mb-3 size-10 opacity-40" />
                    <p class="text-sm">No sites yet.</p>
                </div>
                <a
                    v-for="site in sites"
                    :key="site.id"
                    :href="siteRoutes.show(site)"
                    class="block"
                >
                    <Card class="transition-colors hover:bg-accent/50">
                        <CardContent class="flex items-center justify-between py-4">
                            <div class="flex items-center gap-3">
                                <Globe class="size-5 text-muted-foreground" />
                                <div>
                                    <p class="font-medium">{{ site.domain }}</p>
                                    <p class="text-xs text-muted-foreground">{{ site.path }}</p>
                                </div>
                            </div>
                            <span class="text-xs text-muted-foreground">{{ site.branch }}</span>
                        </CardContent>
                    </Card>
                </a>
            </div>
        </div>
    </div>
</template>
