<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LayoutGrid, Server, ScrollText, Globe, Activity, Clock, PlusSquare, FileText } from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import servers from '@/routes/servers';
import audit from '@/routes/audit';
import provision from '@/routes/provision';
import logs from '@/routes/logs';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Servers',
        href: servers.index(),
        icon: Server,
    },
    {
        title: 'Nginx Sites',
        href: '/nginx',
        icon: Globe,
    },
    {
        title: 'Supervisor',
        href: '/supervisor',
        icon: Activity,
    },
    {
        title: 'Crontab',
        href: '/cron',
        icon: Clock,
    },
    {
        title: 'New Site',
        href: provision.create(),
        icon: PlusSquare,
    },
    {
        title: 'Log Viewer',
        href: logs.show(),
        icon: FileText,
    },
    {
        title: 'Audit Logs',
        href: audit.index(),
        icon: ScrollText,
    },
];

const footerNavItems: NavItem[] = [
    // {
    //     title: 'Repository',
    //     href: 'https://github.com/laravel/vue-starter-kit',
    //     icon: FolderGit2,
    // },
    // {
    //     title: 'Documentation',
    //     href: 'https://laravel.com/docs/starter-kits#vue',
    //     icon: BookOpen,
    // },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
