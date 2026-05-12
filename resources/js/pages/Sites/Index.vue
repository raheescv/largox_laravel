<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    Boxes,
    CheckCircle2,
    Code2,
    FolderGit2,
    Globe,
    PackageCheck,
    PlusCircle,
    Search,
    SlidersHorizontal,
    X,
} from 'lucide-vue-next';
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
            {
                title: pageProps.server.name,
                href: serverRoutes.show.url(pageProps.server),
            },
            {
                title: 'Sites',
                href: serverSiteRoutes.index.url(pageProps.server),
            },
        ],
    }),
});

const props = defineProps<{ server: ServerModel; sites: SiteModel[] }>();
const search = ref('');
const buildFilter = ref<'all' | 'composer' | 'npm' | 'repo'>('all');

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

const filteredSites = computed(() => {
    const q = search.value.trim().toLowerCase();

    return props.sites.filter((site) => {
        const matchesSearch =
            !q ||
            [site.domain, site.path, site.branch, site.repository ?? '']
                .join(' ')
                .toLowerCase()
                .includes(q);

        const matchesBuild =
            buildFilter.value === 'all' ||
            (buildFilter.value === 'composer' && site.composer) ||
            (buildFilter.value === 'npm' && site.npm_build) ||
            (buildFilter.value === 'repo' && Boolean(site.repository));

        return matchesSearch && matchesBuild;
    });
});

const sitesWithRepos = computed(
    () => props.sites.filter((site) => site.repository).length,
);
const composerSites = computed(
    () => props.sites.filter((site) => site.composer).length,
);
const npmSites = computed(
    () => props.sites.filter((site) => site.npm_build).length,
);

function clearFilters() {
    search.value = '';
    buildFilter.value = 'all';
}
</script>

<template>
    <Head title="Sites" />

    <div class="ops-page">
        <section class="ops-hero">
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
            >
                <Heading
                    :title="`Sites on ${server.name}`"
                    description="Deployable web roots, build hooks, repositories, and runtime paths for this server."
                    class="mb-0"
                />
                <div class="grid grid-cols-3 gap-2 sm:min-w-96">
                    <div class="ops-stat">
                        <p class="text-xs text-muted-foreground">Total</p>
                        <p class="mt-1 text-2xl font-semibold">
                            {{ sites.length }}
                        </p>
                    </div>
                    <div class="ops-stat">
                        <p class="text-xs text-muted-foreground">Git</p>
                        <p class="mt-1 text-2xl font-semibold">
                            {{ sitesWithRepos }}
                        </p>
                    </div>
                    <div class="ops-stat">
                        <p class="text-xs text-muted-foreground">Builds</p>
                        <p class="mt-1 text-2xl font-semibold">
                            {{ composerSites + npmSites }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[0.9fr_1.35fr]">
            <Card class="h-fit">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <PlusCircle class="size-5 text-primary" />
                        Add Site
                    </CardTitle>
                    <CardDescription
                        >Register a domain, path, and optional deployment
                        repository.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="grid gap-2">
                            <Label for="domain">Domain</Label>
                            <Input
                                id="domain"
                                v-model="form.domain"
                                placeholder="example.com"
                                required
                            />
                            <InputError :message="form.errors.domain" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="path">Path</Label>
                            <Input
                                id="path"
                                v-model="form.path"
                                placeholder="/var/www/example.com"
                                required
                            />
                            <InputError :message="form.errors.path" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="repository"
                                >Repository
                                <span class="text-muted-foreground"
                                    >(optional)</span
                                ></Label
                            >
                            <Input
                                id="repository"
                                v-model="form.repository"
                                placeholder="git@github.com:org/repo.git"
                            />
                            <InputError :message="form.errors.repository" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="branch">Branch</Label>
                            <Input
                                id="branch"
                                v-model="form.branch"
                                placeholder="main"
                            />
                        </div>
                        <div
                            class="grid gap-3 rounded-lg border bg-muted/40 p-3 sm:grid-cols-2"
                        >
                            <label class="flex items-center gap-2 text-sm">
                                <Checkbox
                                    id="composer"
                                    v-model:checked="form.composer"
                                />
                                Composer install
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <Checkbox
                                    id="npm_build"
                                    v-model:checked="form.npm_build"
                                />
                                npm build
                            </label>
                        </div>
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full"
                        >
                            <PlusCircle class="mr-2 size-4" /> Add Site
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <section class="space-y-3">
                <div class="ops-toolbar">
                    <div class="ops-search">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="search"
                            class="pl-9"
                            placeholder="Search domain, path, branch, or repository"
                        />
                    </div>
                    <div
                        class="flex flex-col gap-2 sm:flex-row sm:items-center"
                    >
                        <div class="relative">
                            <SlidersHorizontal
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <select
                                v-model="buildFilter"
                                class="h-9 rounded-md border border-input bg-background pr-8 pl-9 text-sm shadow-sm"
                            >
                                <option value="all">All sites</option>
                                <option value="repo">Git-backed</option>
                                <option value="composer">Composer</option>
                                <option value="npm">npm build</option>
                            </select>
                        </div>
                        <Button
                            v-if="search || buildFilter !== 'all'"
                            variant="ghost"
                            size="sm"
                            @click="clearFilters"
                        >
                            <X class="mr-2 size-4" />
                            Reset
                        </Button>
                    </div>
                </div>

                <div
                    v-if="sites.length === 0"
                    class="flex flex-col items-center justify-center rounded-lg border border-dashed bg-card/70 py-16 text-center text-muted-foreground"
                >
                    <Globe class="mb-3 size-10 opacity-40" />
                    <p class="text-sm">No sites yet.</p>
                </div>
                <div
                    v-else-if="filteredSites.length === 0"
                    class="flex flex-col items-center justify-center rounded-lg border border-dashed bg-card/70 py-16 text-center text-muted-foreground"
                >
                    <Search class="mb-3 size-10 opacity-40" />
                    <p class="text-sm">No sites match your filters.</p>
                    <Button variant="link" size="sm" @click="clearFilters"
                        >Clear filters</Button
                    >
                </div>
                <div v-else class="ops-list">
                    <a
                        v-for="site in filteredSites"
                        :key="site.id"
                        :href="siteRoutes.show.url(site)"
                        class="ops-list-row"
                    >
                        <div class="flex min-w-0 items-start gap-3">
                            <div
                                class="mt-0.5 rounded-md border bg-background p-2 text-primary"
                            >
                                <Globe class="size-5" />
                            </div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate font-medium">
                                        {{ site.domain }}
                                    </p>
                                    <Badge
                                        variant="outline"
                                        class="font-mono"
                                        >{{ site.branch }}</Badge
                                    >
                                </div>
                                <p
                                    class="mt-1 truncate font-mono text-xs text-muted-foreground"
                                >
                                    {{ site.path }}
                                </p>
                                <p
                                    v-if="site.repository"
                                    class="mt-1 flex items-center gap-1 truncate text-xs text-muted-foreground"
                                >
                                    <FolderGit2 class="size-3.5" />
                                    {{ site.repository }}
                                </p>
                            </div>
                        </div>
                        <div
                            class="flex flex-wrap items-center gap-2 sm:justify-end"
                        >
                            <Badge v-if="site.repository" variant="secondary">
                                <Code2 class="mr-1 size-3" />
                                git
                            </Badge>
                            <Badge v-if="site.composer" variant="secondary">
                                <PackageCheck class="mr-1 size-3" />
                                composer
                            </Badge>
                            <Badge v-if="site.npm_build" variant="secondary">
                                <Boxes class="mr-1 size-3" />
                                npm
                            </Badge>
                            <Badge
                                v-if="
                                    !site.repository &&
                                    !site.composer &&
                                    !site.npm_build
                                "
                                variant="outline"
                            >
                                <CheckCircle2 class="mr-1 size-3" />
                                static
                            </Badge>
                        </div>
                    </a>
                </div>
            </section>
        </div>
    </div>
</template>
