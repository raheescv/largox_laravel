<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
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
import cronRoutes from '@/routes/cron';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Crontab', href: cronRoutes.index() },
        ],
    },
});

const props = defineProps<{
    user: string;
    contents: string;
    error: string | null;
}>();

const form = useForm({
    user: props.user,
    contents: props.contents,
});
const agentError = computed(
    () => (form.errors as Record<string, string>).agent,
);

function submit() {
    form.put(cronRoutes.update.url());
}

function switchUser() {
    window.location.href = cronRoutes.index.url({ query: { user: form.user } });
}
</script>

<template>
    <Head title="Crontab" />

    <div class="space-y-6">
        <Heading title="Crontab" description="Edit crontab for a system user" />

        <div
            v-if="error"
            class="rounded-xl border border-red-300 bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40"
        >
            {{ error }}
        </div>

        <Card>
            <CardHeader>
                <CardTitle>User crontab</CardTitle>
                <CardDescription
                    >Full contents of crontab -u
                    {{ form.user }} -l</CardDescription
                >
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex items-end gap-3">
                    <div class="grid gap-2">
                        <Label for="user">User</Label>
                        <Input
                            id="user"
                            v-model="form.user"
                            class="w-36"
                            @keydown.enter.prevent="switchUser"
                        />
                    </div>
                    <Button variant="outline" @click="switchUser">Load</Button>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <textarea
                        v-model="form.contents"
                        rows="20"
                        class="w-full resize-y rounded-md border border-input bg-background px-3 py-2 font-mono text-xs"
                        placeholder="# m h dom mon dow command"
                    />
                    <p
                        v-if="form.errors.contents"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.contents }}
                    </p>
                    <p v-if="agentError" class="text-sm text-destructive">
                        {{ agentError }}
                    </p>
                    <Button type="submit" :disabled="form.processing"
                        >Save crontab</Button
                    >
                </form>
            </CardContent>
        </Card>
    </div>
</template>
