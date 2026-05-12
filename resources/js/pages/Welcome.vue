<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { dashboard, login, register } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    CheckCircle2,
    Clock3,
    Code2,
    DatabaseZap,
    GitBranch,
    Globe2,
    LockKeyhole,
    ScrollText,
    Server,
    ShieldCheck,
    TerminalSquare,
} from 'lucide-vue-next';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const capabilities = [
    {
        icon: Server,
        title: 'Server Command Center',
        body: 'Register servers, monitor agent health, and run operational actions without leaving the panel.',
    },
    {
        icon: Globe2,
        title: 'Site Provisioning',
        body: 'Create deployable domains with repository, path, branch, Nginx, Supervisor, and cron details in one guided flow.',
    },
    {
        icon: GitBranch,
        title: 'Release Tracking',
        body: 'Trigger deployments, capture output, record failures, and keep every site release visible.',
    },
    {
        icon: ScrollText,
        title: 'Audit & Logs',
        body: 'Review command history, server responses, and live log files when production needs a closer look.',
    },
];

const workflow = [
    'Connect a Go agent server',
    'Provision the web root and runtime files',
    'Deploy from the selected Git branch',
    'Watch logs, audits, and service status',
];

const previewRows = [
    { site: 'api.largox.app', status: 'Live', branch: 'main', tone: 'emerald' },
    {
        site: 'panel.largox.app',
        status: 'Deploying',
        branch: 'release',
        tone: 'amber',
    },
    { site: 'docs.largox.app', status: 'Ready', branch: 'stable', tone: 'sky' },
];
</script>

<template>
    <Head title="Largox">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <main
        class="min-h-screen overflow-hidden bg-[#f6f8fb] text-[#101828] dark:bg-[#07111f] dark:text-white"
    >
        <section
            class="relative border-b border-slate-200/80 bg-[linear-gradient(135deg,#f8fafc_0%,#eef6ff_45%,#f6f7f2_100%)] dark:border-white/10 dark:bg-[linear-gradient(135deg,#06111f_0%,#10253d_54%,#171a24_100%)]"
        >
            <div
                class="mx-auto flex w-full max-w-7xl items-center justify-between px-5 py-5 sm:px-8 lg:px-10"
            >
                <Link href="/" class="flex items-center gap-3">
                    <span
                        class="flex size-10 items-center justify-center rounded-md bg-[#0f5ea8] text-white shadow-sm dark:bg-white dark:text-[#0b1628]"
                    >
                        <AppLogoIcon class-name="size-6 fill-current" />
                    </span>
                    <span class="text-lg font-semibold tracking-normal"
                        >Largox</span
                    >
                </Link>

                <nav class="flex items-center gap-2 text-sm font-medium">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="inline-flex h-10 items-center gap-2 rounded-md bg-[#101828] px-4 text-white transition hover:bg-[#253044] dark:bg-white dark:text-[#101828]"
                    >
                        Dashboard
                        <ArrowRight class="size-4" />
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="inline-flex h-10 items-center rounded-md border border-slate-300 bg-white/75 px-4 text-[#101828] shadow-sm transition hover:border-[#0f5ea8] dark:border-white/15 dark:bg-white/10 dark:text-white"
                        >
                            Log in
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="hidden h-10 items-center gap-2 rounded-md bg-[#101828] px-4 text-white shadow-sm transition hover:bg-[#253044] sm:inline-flex dark:bg-white dark:text-[#101828]"
                        >
                            Register
                            <ArrowRight class="size-4" />
                        </Link>
                    </template>
                </nav>
            </div>

            <div
                class="mx-auto grid w-full max-w-7xl gap-8 px-5 pt-6 pb-12 sm:px-8 sm:pb-14 lg:grid-cols-[0.86fr_1.14fr] lg:px-10 lg:pt-8 lg:pb-16"
            >
                <div class="flex flex-col justify-center">
                    <div
                        class="mb-6 inline-flex w-fit items-center gap-2 rounded-md border border-[#0f5ea8]/20 bg-white/80 px-3 py-2 text-sm font-medium text-[#0f5ea8] shadow-sm dark:border-white/15 dark:bg-white/10 dark:text-[#b7dcff]"
                    >
                        <ShieldCheck class="size-4" />
                        Laravel hosting control panel
                    </div>

                    <h1
                        class="max-w-4xl text-4xl leading-tight font-semibold tracking-normal text-[#0b1628] sm:text-5xl dark:text-white"
                    >
                        Deploy and operate Laravel sites from one control room.
                    </h1>

                    <p
                        class="mt-6 max-w-2xl text-base leading-8 text-slate-600 sm:text-lg dark:text-slate-300"
                    >
                        Largox brings servers, sites, Nginx files, queue
                        workers, cron entries, deployments, logs, and audit
                        trails into a calm interface made for day-to-day
                        production work.
                    </p>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="dashboard()"
                            class="inline-flex h-12 items-center justify-center gap-2 rounded-md bg-[#0f5ea8] px-6 text-sm font-semibold text-white shadow-sm transition hover:bg-[#0b4d8a]"
                        >
                            Open dashboard
                            <ArrowRight class="size-4" />
                        </Link>
                        <Link
                            v-else
                            :href="login()"
                            class="inline-flex h-12 items-center justify-center gap-2 rounded-md bg-[#0f5ea8] px-6 text-sm font-semibold text-white shadow-sm transition hover:bg-[#0b4d8a]"
                        >
                            Access Largox
                            <ArrowRight class="size-4" />
                        </Link>
                        <a
                            href="#basic-details"
                            class="inline-flex h-12 items-center justify-center rounded-md border border-slate-300 bg-white/75 px-6 text-sm font-semibold text-[#101828] shadow-sm transition hover:border-[#0f5ea8] dark:border-white/15 dark:bg-white/10 dark:text-white"
                        >
                            Basic details
                        </a>
                    </div>
                </div>

                <div
                    class="relative hidden items-center sm:flex lg:justify-end"
                >
                    <div
                        class="w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-2xl shadow-slate-900/10 dark:border-white/10 dark:bg-[#0a1626] dark:shadow-black/30"
                    >
                        <div
                            class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-5 py-4 dark:border-white/10 dark:bg-white/5"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="flex size-9 items-center justify-center rounded-md bg-[#0f5ea8] text-white"
                                >
                                    <TerminalSquare class="size-5" />
                                </span>
                                <div>
                                    <p class="text-sm font-semibold">
                                        Production Overview
                                    </p>
                                    <p
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        largox-agent connected
                                    </p>
                                </div>
                            </div>
                            <span
                                class="inline-flex items-center gap-2 rounded-md bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300"
                            >
                                <Activity class="size-3.5" />
                                Healthy
                            </span>
                        </div>

                        <div class="grid grid-cols-3 gap-2 p-4 sm:hidden">
                            <div
                                class="rounded-md border border-slate-200 p-3 dark:border-white/10"
                            >
                                <p class="text-xl font-semibold">18</p>
                                <p
                                    class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Sites
                                </p>
                            </div>
                            <div
                                class="rounded-md border border-slate-200 p-3 dark:border-white/10"
                            >
                                <p class="text-xl font-semibold">7</p>
                                <p
                                    class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Cron
                                </p>
                            </div>
                            <div
                                class="rounded-md border border-slate-200 p-3 dark:border-white/10"
                            >
                                <p class="text-xl font-semibold">Live</p>
                                <p
                                    class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Agent
                                </p>
                            </div>
                        </div>

                        <div
                            class="hidden gap-0 sm:grid lg:grid-cols-[0.72fr_1.28fr]"
                        >
                            <aside
                                class="border-b border-slate-200 bg-[#101828] p-5 text-white lg:border-r lg:border-b-0 dark:border-white/10"
                            >
                                <div class="grid gap-3">
                                    <div
                                        class="rounded-md bg-white/10 p-4 ring-1 ring-white/10"
                                    >
                                        <p
                                            class="text-xs font-medium tracking-[0.16em] text-slate-300 uppercase"
                                        >
                                            Server
                                        </p>
                                        <p class="mt-2 text-xl font-semibold">
                                            Mumbai-01
                                        </p>
                                        <p class="mt-1 text-sm text-slate-300">
                                            192.168.10.24:8443
                                        </p>
                                    </div>
                                    <div
                                        class="rounded-md bg-white/10 p-4 ring-1 ring-white/10"
                                    >
                                        <p
                                            class="text-xs font-medium tracking-[0.16em] text-slate-300 uppercase"
                                        >
                                            Last deploy
                                        </p>
                                        <p class="mt-2 text-xl font-semibold">
                                            04 min ago
                                        </p>
                                        <p class="mt-1 text-sm text-slate-300">
                                            commit 9f24c1a
                                        </p>
                                    </div>
                                </div>
                            </aside>

                            <div class="p-5">
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <div
                                        class="rounded-md border border-slate-200 p-4 dark:border-white/10"
                                    >
                                        <DatabaseZap
                                            class="mb-3 size-5 text-[#0f5ea8] dark:text-[#8cc8ff]"
                                        />
                                        <p class="text-2xl font-semibold">18</p>
                                        <p
                                            class="text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            Sites
                                        </p>
                                    </div>
                                    <div
                                        class="rounded-md border border-slate-200 p-4 dark:border-white/10"
                                    >
                                        <Clock3
                                            class="mb-3 size-5 text-[#996b00] dark:text-[#ffd36f]"
                                        />
                                        <p class="text-2xl font-semibold">7</p>
                                        <p
                                            class="text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            Cron jobs
                                        </p>
                                    </div>
                                    <div
                                        class="rounded-md border border-slate-200 p-4 dark:border-white/10"
                                    >
                                        <LockKeyhole
                                            class="mb-3 size-5 text-emerald-700 dark:text-emerald-300"
                                        />
                                        <p class="text-2xl font-semibold">
                                            Signed
                                        </p>
                                        <p
                                            class="text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            Agent calls
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="mt-5 overflow-hidden rounded-md border border-slate-200 dark:border-white/10"
                                >
                                    <div
                                        v-for="row in previewRows"
                                        :key="row.site"
                                        class="grid grid-cols-[1fr_auto] gap-3 border-b border-slate-200 px-4 py-3 last:border-b-0 dark:border-white/10"
                                    >
                                        <div class="min-w-0">
                                            <p class="truncate font-medium">
                                                {{ row.site }}
                                            </p>
                                            <p
                                                class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                branch: {{ row.branch }}
                                            </p>
                                        </div>
                                        <span
                                            class="h-fit rounded-md px-2.5 py-1 text-xs font-semibold"
                                            :class="{
                                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300':
                                                    row.tone === 'emerald',
                                                'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300':
                                                    row.tone === 'amber',
                                                'bg-sky-50 text-sky-700 dark:bg-sky-400/10 dark:text-sky-300':
                                                    row.tone === 'sky',
                                            }"
                                        >
                                            {{ row.status }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="basic-details" class="bg-white py-16 dark:bg-[#081322]">
            <div class="mx-auto w-full max-w-7xl px-5 sm:px-8 lg:px-10">
                <div
                    class="flex flex-col justify-between gap-6 border-b border-slate-200 pb-8 md:flex-row md:items-end dark:border-white/10"
                >
                    <div>
                        <p
                            class="text-sm font-semibold tracking-[0.16em] text-[#0f5ea8] uppercase dark:text-[#8cc8ff]"
                        >
                            Basic Details
                        </p>
                        <h2
                            class="mt-3 max-w-3xl text-3xl font-semibold tracking-normal sm:text-4xl"
                        >
                            Everything a compact hosting operations team needs
                            in the first screen.
                        </h2>
                    </div>
                    <p
                        class="max-w-md text-sm leading-7 text-slate-600 dark:text-slate-300"
                    >
                        Built for Laravel projects that need fast deployment,
                        clear configuration control, and traceable server
                        actions.
                    </p>
                </div>

                <div class="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <article
                        v-for="capability in capabilities"
                        :key="capability.title"
                        class="rounded-lg border border-slate-200 bg-slate-50/70 p-5 dark:border-white/10 dark:bg-white/5"
                    >
                        <component
                            :is="capability.icon"
                            class="size-6 text-[#0f5ea8] dark:text-[#8cc8ff]"
                        />
                        <h3 class="mt-5 text-lg font-semibold">
                            {{ capability.title }}
                        </h3>
                        <p
                            class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300"
                        >
                            {{ capability.body }}
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <section class="bg-[#eef3f7] py-16 dark:bg-[#0b1828]">
            <div
                class="mx-auto grid w-full max-w-7xl gap-8 px-5 sm:px-8 lg:grid-cols-[0.9fr_1.1fr] lg:px-10"
            >
                <div>
                    <p
                        class="text-sm font-semibold tracking-[0.16em] text-[#6f4e00] uppercase dark:text-[#ffd36f]"
                    >
                        Workflow
                    </p>
                    <h2 class="mt-3 text-3xl font-semibold tracking-normal">
                        From empty server to running site, with fewer loose
                        ends.
                    </h2>
                </div>

                <ol class="grid gap-3">
                    <li
                        v-for="(item, index) in workflow"
                        :key="item"
                        class="flex items-center gap-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5"
                    >
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-md bg-[#101828] text-sm font-semibold text-white dark:bg-white dark:text-[#101828]"
                        >
                            {{ index + 1 }}
                        </span>
                        <span class="font-medium">{{ item }}</span>
                        <CheckCircle2
                            class="ml-auto size-5 shrink-0 text-emerald-600 dark:text-emerald-300"
                        />
                    </li>
                </ol>
            </div>
        </section>

        <section class="bg-[#101828] py-12 text-white">
            <div
                class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-5 sm:px-8 md:flex-row md:items-center md:justify-between lg:px-10"
            >
                <div>
                    <div class="flex items-center gap-3">
                        <Code2 class="size-6 text-[#8cc8ff]" />
                        <p class="text-lg font-semibold">Largox Project</p>
                    </div>
                    <p class="mt-2 max-w-2xl text-sm leading-7 text-slate-300">
                        A Laravel + Inertia control panel for connected servers,
                        deployable sites, service configuration, and production
                        visibility.
                    </p>
                </div>
                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                    class="inline-flex h-12 items-center justify-center gap-2 rounded-md bg-white px-6 text-sm font-semibold text-[#101828] transition hover:bg-[#e9f3ff]"
                >
                    Go to dashboard
                    <ArrowRight class="size-4" />
                </Link>
                <Link
                    v-else
                    :href="login()"
                    class="inline-flex h-12 items-center justify-center gap-2 rounded-md bg-white px-6 text-sm font-semibold text-[#101828] transition hover:bg-[#e9f3ff]"
                >
                    Log in to continue
                    <ArrowRight class="size-4" />
                </Link>
            </div>
        </section>
    </main>
</template>
