<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { BreadcrumbItem, Order } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    ClipboardList,
    DollarSign,
    Package,
    ShoppingCart,
    TrendingUp,
    Users
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    isAdmin: boolean;
    stats: {
        total_orders?: number;
        pending_orders?: number;
        total_revenue?: string;
        total_users?: number;
        total_products?: number;
        completed_orders?: number;
        total_spent?: string;
    };
    recent_orders: Order[];
    weekly_stats?: Array<{
        date: string;
        orders_count: number;
        revenue: string;
    }>;
    top_products?: Array<{
        product_name: string;
        total_sold: number;
        total_revenue: string;
    }>;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const formatCurrency = (amount: string | number) => {
    return new Intl.NumberFormat('es-VE', {
        style: 'currency',
        currency: 'USD',
    }).format(Number(amount));
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('es-VE', {
        month: 'short',
        day: 'numeric',
    });
};

const getStatusLabel = (status: Order['status']) => {
    const labels = {
        pending_payment: 'Pendiente',
        paid: 'Pagado',
        shipped: 'Enviado',
        completed: 'Completado',
        cancelled: 'Cancelado',
        refunded: 'Reembolsado',
    };
    return labels[status] || status;
};

const getStatusVariant = (status: Order['status']) => {
    const variants = {
        pending_payment: 'secondary',
        paid: 'default',
        shipped: 'default',
        completed: 'default',
        cancelled: 'destructive',
        refunded: 'outline',
    };
    return variants[status] || 'secondary';
};

const totalRevenue = computed(() => {
    if (props.isAdmin) {
        return props.stats.total_revenue || '0';
    }
    return props.stats.total_spent || '0';
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Encabezado con gradiente corporativo -->
            <div class="relative overflow-hidden rounded-xl p-8 text-white" style="background: linear-gradient(135deg, #0b3c87 0%, #1a5bb8 100%);">
                <div class="relative z-10">
                    <h1 class="text-3xl font-bold mb-2">
                        ¡Bienvenido de vuelta! 👋
                    </h1>
                    <p class="text-white/90">
                        {{ isAdmin ? 'Panel de administración' : 'Gestiona tus pedidos y compras' }}
                    </p>
                </div>
                <!-- Decoración de fondo -->
                <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full -mr-32 -mt-32"></div>
                <div class="absolute bottom-0 right-20 w-40 h-40 bg-white/10 rounded-full"></div>
            </div>

            <!-- Estadísticas principales -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <!-- Card: Total de Órdenes -->
                <Card class="border-l-4 hover:shadow-lg transition-shadow" style="border-left-color: #0b3c87;">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">
                            Total de Órdenes
                        </CardTitle>
                        <div class="p-2 rounded-lg" style="background-color: #0b3c87;">
                            <ShoppingCart class="h-4 w-4 text-white" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold" style="color: #0b3c87;">
                            {{ stats.total_orders || 0 }}
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">
                            {{ isAdmin ? 'Órdenes en el sistema' : 'Tus órdenes totales' }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Card: Pendientes -->
                <Card class="border-l-4 hover:shadow-lg transition-shadow border-l-orange-500">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">
                            {{ isAdmin ? 'Órdenes Pendientes' : 'Pedidos Pendientes' }}
                        </CardTitle>
                        <div class="p-2 rounded-lg bg-orange-500">
                            <ClipboardList class="h-4 w-4 text-white" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-orange-600">
                            {{ stats.pending_orders || 0 }}
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">
                            Esperando pago
                        </p>
                    </CardContent>
                </Card>

                <!-- Card: Ingresos/Gastado -->
                <Card class="border-l-4 hover:shadow-lg transition-shadow border-l-green-500">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">
                            {{ isAdmin ? 'Ingresos Totales' : 'Total Gastado' }}
                        </CardTitle>
                        <div class="p-2 rounded-lg bg-green-500">
                            <DollarSign class="h-4 w-4 text-white" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-green-600">
                            {{ formatCurrency(totalRevenue) }}
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">
                            {{ isAdmin ? 'Órdenes pagadas' : 'En todas tus compras' }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Card: Usuarios/Completadas -->
                <Card v-if="isAdmin" class="border-l-4 hover:shadow-lg transition-shadow" style="border-left-color: #0b3c87;">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">
                            Usuarios Totales
                        </CardTitle>
                        <div class="p-2 rounded-lg" style="background-color: #0b3c87;">
                            <Users class="h-4 w-4 text-white" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold" style="color: #0b3c87;">
                            {{ stats.total_users || 0 }}
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">
                            Clientes registrados
                        </p>
                    </CardContent>
                </Card>

                <Card v-else class="border-l-4 hover:shadow-lg transition-shadow border-l-green-500">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">
                            Órdenes Completadas
                        </CardTitle>
                        <div class="p-2 rounded-lg bg-green-500">
                            <Package class="h-4 w-4 text-white" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-green-600">
                            {{ stats.completed_orders || 0 }}
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">
                            Entregadas con éxito
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <!-- Órdenes Recientes -->
                <Card class="md:col-span-2 lg:col-span-1">
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle>Órdenes Recientes</CardTitle>
                                <CardDescription>
                                    {{ isAdmin ? 'Últimas 5 órdenes del sistema' : 'Tus últimas 5 órdenes' }}
                                </CardDescription>
                            </div>
                            <Link v-if="isAdmin" :href="'/admin/orders'">
                                <Button variant="outline" size="sm">
                                    Ver todas
                                    <ArrowRight class="ml-2 h-4 w-4" />
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="recent_orders.length === 0" class="text-center py-8 text-muted-foreground">
                            No hay órdenes recientes
                        </div>
                        <div v-else class="space-y-4">
                            <div
                                v-for="order in recent_orders"
                                :key="order.id"
                                class="flex items-center justify-between p-3 rounded-lg border hover:bg-accent/50 transition-colors cursor-pointer"
                                @click="isAdmin && $inertia.visit(`/admin/orders/${order.id}`)"
                            >
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <p class="font-medium">Orden #{{ order.id }}</p>
                                        <Badge :variant="getStatusVariant(order.status)" class="text-xs">
                                            {{ getStatusLabel(order.status) }}
                                        </Badge>
                                    </div>
                                    <p class="text-sm text-muted-foreground">
                                        {{ isAdmin ? order.user?.name : formatDate(order.created_at) }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold" style="color: #0b3c87;">
                                        {{ formatCurrency(order.total) }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ formatDate(order.created_at) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Productos más vendidos (Solo Admin) -->
                <Card v-if="isAdmin && top_products" class="md:col-span-2 lg:col-span-1">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <TrendingUp class="h-5 w-5" style="color: #0b3c87;" />
                            Top 5 Productos
                        </CardTitle>
                        <CardDescription>Productos más vendidos (últimos 30 días)</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="top_products.length === 0" class="text-center py-8 text-muted-foreground">
                            No hay datos de ventas
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="(product, index) in top_products"
                                :key="index"
                                class="flex items-center justify-between p-3 rounded-lg bg-accent/20"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex items-center justify-center w-8 h-8 rounded-full text-white font-bold text-sm"
                                        style="background-color: #0b3c87;"
                                    >
                                        {{ index + 1 }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-sm">{{ product.product_name }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ product.total_sold }} vendidos
                                        </p>
                                    </div>
                                </div>
                                <p class="font-semibold" style="color: #0b3c87;">
                                    {{ formatCurrency(product.total_revenue) }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Actividad Semanal (Solo Admin) -->
                <Card v-if="isAdmin && weekly_stats" class="md:col-span-2">
                    <CardHeader>
                        <CardTitle>Actividad de la Última Semana</CardTitle>
                        <CardDescription>Resumen de órdenes e ingresos</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="weekly_stats.length === 0" class="text-center py-8 text-muted-foreground">
                            No hay actividad esta semana
                        </div>
                        <div v-else class="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Fecha</TableHead>
                                        <TableHead class="text-right">Órdenes</TableHead>
                                        <TableHead class="text-right">Ingresos</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="stat in weekly_stats" :key="stat.date">
                                        <TableCell class="font-medium">
                                            {{ formatDate(stat.date) }}
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <Badge variant="secondary">{{ stat.orders_count }}</Badge>
                                        </TableCell>
                                        <TableCell class="text-right font-semibold" style="color: #0b3c87;">
                                            {{ formatCurrency(stat.revenue) }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Call to Action para clientes -->
            <Card v-if="!isAdmin" class="border-2" style="border-color: #0b3c87;">
                <CardContent class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold" style="color: #0b3c87;">
                                ¿Listo para tu próxima compra?
                            </h3>
                            <p class="text-sm text-muted-foreground mt-1">
                                Explora nuestro catálogo y encuentra los mejores productos
                            </p>
                        </div>
                        <Button size="lg" style="background-color: #0b3c87; color: white;">
                            Ver Productos
                            <ArrowRight class="ml-2 h-4 w-4" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Animaciones suaves para las cards */
.hover\:shadow-lg {
    transition: all 0.3s ease;
}

.hover\:shadow-lg:hover {
    transform: translateY(-2px);
}
</style>
