<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, ClipboardList, Folder, LayoutGrid, Truck } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: '/dashboard',
            icon: LayoutGrid,
        },
    ];

    // Agregar opciones de admin si el usuario tiene el rol
    if (page.props.auth.user?.is_admin) {
        items.push(
            {
                title: 'Gestión de Órdenes',
                href: '/admin/orders',
                icon: ClipboardList,
            },
            {
                title: 'Deliveries',
                href: '/admin/deliveries',
                icon: Truck,
            }
        );
    }

    return items;
});

const footerNavItems: NavItem[] = [
    {
        title: 'Github Repo',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="corporate-sidebar">
        <SidebarHeader class="corporate-header">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
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

<style scoped>
.corporate-sidebar {
    border-right: 1px solid #e5e7eb;
}

.corporate-header {
    border-bottom: 2px solid #0b3c87;
}

:deep(.sidebar-menu-button[data-active="true"]) {
    background-color: #0b3c87 !important;
    color: white !important;
}

:deep(.sidebar-menu-button:hover:not([data-active="true"])) {
    background-color: rgba(11, 60, 135, 0.1);
    color: #0b3c87;
}
</style>
