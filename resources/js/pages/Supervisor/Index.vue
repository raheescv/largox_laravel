<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { PlusCircle } from 'lucide-vue-next';
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
import supervisorRoutes from '@/routes/supervisor';
import { dashboard } from '@/routes';

type SupervisorFile = { name: string; size: number };
type ExecutorResult = { stdout?: string; stderr?: string; exit_code?: number } | null;

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Supervisor', href: supervisorRoutes.index() },
        ],
    },
});

defineProps<{
    files: SupervisorFile[];
    status: ExecutorResult;
    error: string | null;
}>();

const storeForm = useForm({
    name: '',
    contents: defaultConfig(),
});

function defaultConfig(): string {
    return `[program:laravel-worker]
command=php /var/www/example.com/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/worker.log
stopwaitsecs=3600`;
}

function submitStore() {
    storeForm.post(supervisorRoutes.store.url());
}

const controlForm = useForm({ program: '', action: 'restart' as 'start' | 'stop' | 'restart' });
function control(program: string, action: 'start' | 'stop' | 'restart') {
    controlForm.program = program;
    controlForm.action = action;
    controlForm.post(supervisorRoutes.control.url());
}

const deleteForm = useForm({});
function destroy(name: string) {
    if (confirm(`Remove supervisor config "${name}"?`)) {
        deleteForm.delete(supervisorRoutes.destroy.url({ name }));
    }
}
</script>

<template>
    <Head title="Supervisor" />

    <div class="space-y-6">
        <Heading title="Supervisor" description="Manage supervisor program configs" />

        <div v-if="error" class="rounded-xl border border-red-300 bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40">
            {{ error }}
        </div>

        <!-- supervisorctl status output -->
        <Card v-if="status">
            <CardHeader>
                <CardTitle>Status</CardTitle>
            </CardHeader>
            <CardContent>
                <pre class="text-xs bg-muted rounded p-3 overflow-x-auto whitespace-pre-wrap">{{ status.stdout ?? status }}</pre>
            </CardContent>
        </Card>

        <div class="grid gap-6 md:grid-cols-2">
            <!-- Add config -->
            <Card>
                <CardHeader>
                    <CardTitle>New Program</CardTitle>
                    <CardDescription>Add a .conf file to /etc/supervisor/conf.d</CardDescription>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submitStore">
                        <div class="grid gap-2">
                            <Label for="name">Filename (must end in .conf)</Label>
                            <Input id="name" v-model="storeForm.name" placeholder="laravel-worker.conf" required />
                            <p v-if="storeForm.errors.name" class="text-sm text-destructive">{{ storeForm.errors.name }}</p>
                        </div>
                        <div class="grid gap-2">
                            <Label for="contents">Config</Label>
                            <textarea
                                id="contents"
                                v-model="storeForm.contents"
                                rows="12"
                                class="w-full font-mono text-xs rounded-md border border-input bg-background px-3 py-2 resize-y"
                                required
                            />
                        </div>
                        <p v-if="storeForm.errors.agent" class="text-sm text-destructive">{{ storeForm.errors.agent }}</p>
                        <Button type="submit" :disabled="storeForm.processing" class="w-full">
                            <PlusCircle class="mr-2 size-4" /> Add Program
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <!-- File list -->
            <div class="space-y-3">
                <div v-if="files.length === 0" class="flex flex-col items-center justify-center rounded-xl border border-dashed py-12 text-center text-muted-foreground">
                    <p class="text-sm">No .conf files yet.</p>
                </div>
                <Card v-for="f in files" :key="f.name">
                    <CardContent class="py-3 px-4 space-y-2">
                        <div class="flex items-center justify-between">
                            <a :href="supervisorRoutes.show.url({ name: f.name })" class="font-mono text-sm hover:underline">{{ f.name }}</a>
                            <Button
                                variant="destructive"
                                size="sm"
                                :disabled="deleteForm.processing"
                                @click="destroy(f.name)"
                            >Remove</Button>
                        </div>
                        <div class="flex gap-2">
                            <Button variant="outline" size="sm" :disabled="controlForm.processing" @click="control(f.name.replace('.conf', ''), 'start')">Start</Button>
                            <Button variant="outline" size="sm" :disabled="controlForm.processing" @click="control(f.name.replace('.conf', ''), 'stop')">Stop</Button>
                            <Button variant="outline" size="sm" :disabled="controlForm.processing" @click="control(f.name.replace('.conf', ''), 'restart')">Restart</Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
