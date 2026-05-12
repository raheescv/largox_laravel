<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import nginxRoutes from '@/routes/nginx';
import { dashboard } from '@/routes';

type NginxSite = { name: string; contents: string; enabled: boolean } | null;

defineOptions({
    layout: (pageProps: { name: string }) => ({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Nginx Sites', href: nginxRoutes.index() },
            {
                title: pageProps.name,
                href: nginxRoutes.show.url({ name: pageProps.name }),
            },
        ],
    }),
});

const props = defineProps<{
    server: { id: number; name: string; host: string };
    site: NginxSite;
    name: string;
    error: string | null;
}>();

const updateForm = useForm({
    contents: props.site?.contents ?? '',
});
const updateAgentError = computed(
    () => (updateForm.errors as Record<string, string>).agent,
);

function submitUpdate() {
    updateForm.put(nginxRoutes.update.url({ name: props.name }));
}

const enableForm = useForm({});
const disableForm = useForm({});

function enable() {
    enableForm.post(nginxRoutes.enable.url({ name: props.name }));
}
function disable() {
    disableForm.post(nginxRoutes.disable.url({ name: props.name }));
}

const deleteForm = useForm({});
function destroy() {
    if (confirm(`Delete "${props.name}"?`)) {
        deleteForm.delete(nginxRoutes.destroy.url({ name: props.name }));
    }
}
</script>

<template>
    <Head :title="`nginx: ${name}`" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <Heading :title="name" description="nginx site configuration" />
                <Badge
                    v-if="site"
                    :variant="site.enabled ? 'default' : 'secondary'"
                >
                    {{ site.enabled ? 'enabled' : 'disabled' }}
                </Badge>
            </div>
            <div class="flex gap-2">
                <Button
                    v-if="site && !site.enabled"
                    variant="outline"
                    size="sm"
                    :disabled="enableForm.processing"
                    @click="enable"
                    >Enable</Button
                >
                <Button
                    v-if="site?.enabled"
                    variant="outline"
                    size="sm"
                    :disabled="disableForm.processing"
                    @click="disable"
                    >Disable</Button
                >
                <Button
                    variant="destructive"
                    size="sm"
                    :disabled="deleteForm.processing"
                    @click="destroy"
                >
                    <Trash2 class="mr-2 size-4" /> Delete
                </Button>
            </div>
        </div>

        <div
            v-if="error"
            class="rounded-xl border border-red-300 bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40"
        >
            {{ error }}
        </div>

        <Card v-if="site">
            <CardHeader>
                <CardTitle>Config</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submitUpdate" class="space-y-4">
                    <textarea
                        v-model="updateForm.contents"
                        rows="24"
                        class="w-full resize-y rounded-md border border-input bg-background px-3 py-2 font-mono text-xs"
                        required
                    />
                    <p
                        v-if="updateForm.errors.contents"
                        class="text-sm text-destructive"
                    >
                        {{ updateForm.errors.contents }}
                    </p>
                    <p v-if="updateAgentError" class="text-sm text-destructive">
                        {{ updateAgentError }}
                    </p>
                    <Button type="submit" :disabled="updateForm.processing"
                        >Save &amp; reload nginx</Button
                    >
                </form>
            </CardContent>
        </Card>

        <Card v-else-if="!error">
            <CardContent class="py-8 text-center text-sm text-muted-foreground">
                Site config not found.
            </CardContent>
        </Card>
    </div>
</template>
