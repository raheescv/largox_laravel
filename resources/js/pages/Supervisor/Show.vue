<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import supervisorRoutes from '@/routes/supervisor';
import { dashboard } from '@/routes';

type SupervisorFile = { name: string; contents: string } | null;

defineOptions({
    layout: (pageProps: { name: string }) => ({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Supervisor', href: supervisorRoutes.index() },
            { title: pageProps.name, href: supervisorRoutes.show.url({ name: pageProps.name }) },
        ],
    }),
});

const props = defineProps<{
    name: string;
    file: SupervisorFile;
    error: string | null;
}>();

const updateForm = useForm({
    contents: props.file?.contents ?? '',
});

function submitUpdate() {
    updateForm.put(supervisorRoutes.update.url({ name: props.name }));
}

const deleteForm = useForm({});
function destroy() {
    if (confirm(`Remove "${props.name}"?`)) {
        deleteForm.delete(supervisorRoutes.destroy.url({ name: props.name }));
    }
}
</script>

<template>
    <Head :title="`supervisor: ${name}`" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <Heading :title="name" description="Supervisor program config" />
            <Button variant="destructive" size="sm" :disabled="deleteForm.processing" @click="destroy">
                <Trash2 class="mr-2 size-4" /> Remove
            </Button>
        </div>

        <div v-if="error" class="rounded-xl border border-red-300 bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40">
            {{ error }}
        </div>

        <Card v-if="file">
            <CardHeader>
                <CardTitle>Config</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submitUpdate" class="space-y-4">
                    <textarea
                        v-model="updateForm.contents"
                        rows="20"
                        class="w-full font-mono text-xs rounded-md border border-input bg-background px-3 py-2 resize-y"
                        required
                    />
                    <p v-if="updateForm.errors.contents" class="text-sm text-destructive">{{ updateForm.errors.contents }}</p>
                    <p v-if="updateForm.errors.agent" class="text-sm text-destructive">{{ updateForm.errors.agent }}</p>
                    <Button type="submit" :disabled="updateForm.processing">Save</Button>
                </form>
            </CardContent>
        </Card>

        <Card v-else-if="!error">
            <CardContent class="py-8 text-center text-sm text-muted-foreground">
                Config file not found.
            </CardContent>
        </Card>
    </div>
</template>
