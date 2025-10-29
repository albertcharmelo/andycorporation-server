<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { BreadcrumbItem, Order, PaginatedData } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Eye, RefreshCw, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    orders: PaginatedData<Order>;
    filters: {
        status: string;
        payment_method: string;
        search: string;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Admin',
        href: '/admin/dashboard',
    },
    {
        title: 'Órdenes',
        href: '/admin/orders',
    },
];

const localFilters = ref({
    status: props.filters.status || 'all',
    payment_method: props.filters.payment_method || '',
    search: props.filters.search || '',
});

const statusOptions = [
    { value: 'all', label: 'Todos' },
    { value: 'pending_payment', label: 'Pago Pendiente' },
    { value: 'paid', label: 'Pagado' },
    { value: 'shipped', label: 'Enviado' },
    { value: 'completed', label: 'Completado' },
    { value: 'cancelled', label: 'Cancelado' },
    { value: 'refunded', label: 'Reembolsado' },
];

const paymentMethodOptions = [
    { value: '', label: 'Todos' },
    { value: 'manual_transfer', label: 'Transferencia Manual' },
    { value: 'mobile_payment', label: 'Pago Móvil' },
    { value: 'credit_card', label: 'Tarjeta de Crédito' },
    { value: 'paypal', label: 'PayPal' },
    { value: 'binance', label: 'Binance' },
];

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

const getStatusLabel = (status: Order['status']) => {
    const labels = {
        pending_payment: 'Pago Pendiente',
        paid: 'Pagado',
        shipped: 'Enviado',
        completed: 'Completado',
        cancelled: 'Cancelado',
        refunded: 'Reembolsado',
    };
    return labels[status] || status;
};

const getPaymentMethodLabel = (method: Order['payment_method']) => {
    const labels = {
        manual_transfer: 'Transferencia',
        mobile_payment: 'Pago Móvil',
        credit_card: 'Tarjeta',
        paypal: 'PayPal',
        binance: 'Binance',
    };
    return labels[method] || method;
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('es-VE', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatCurrency = (amount: string | number) => {
    return new Intl.NumberFormat('es-VE', {
        style: 'currency',
        currency: 'USD',
    }).format(Number(amount));
};

const applyFilters = () => {
    router.get('/admin/orders', localFilters.value, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    localFilters.value = {
        status: 'all',
        payment_method: '',
        search: '',
    };
    applyFilters();
};

const viewOrderDetail = (orderId: number) => {
    router.visit(`/admin/orders/${orderId}`);
};

const changePage = (page: number) => {
    router.get('/admin/orders', {
        ...localFilters.value,
        page,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const totalPages = computed(() => props.orders.last_page);
const currentPage = computed(() => props.orders.current_page);
</script>

<template>
    <Head title="Gestión de Órdenes - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Gestión de Órdenes</h1>
                    <p class="text-sm text-muted-foreground">
                        Administra todas las órdenes del sistema
                    </p>
                </div>
            </div>

            <!-- Filtros -->
            <Card>
                <CardHeader>
                    <CardTitle>Filtros</CardTitle>
                    <CardDescription>Filtra las órdenes por estado, método de pago o busca por referencia/cliente</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-4">
                        <div class="space-y-2">
                            <Label for="status">Estado</Label>
                            <Select v-model="localFilters.status">
                                <SelectTrigger id="status">
                                    <SelectValue placeholder="Seleccionar estado" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in statusOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <Label for="payment-method">Método de Pago</Label>
                            <Select v-model="localFilters.payment_method">
                                <SelectTrigger id="payment-method">
                                    <SelectValue placeholder="Seleccionar método" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in paymentMethodOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <Label for="search">Buscar</Label>
                            <div class="relative">
                                <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    id="search"
                                    v-model="localFilters.search"
                                    type="text"
                                    placeholder="Referencia o cliente..."
                                    class="pl-8"
                                    @keydown.enter="applyFilters"
                                />
                            </div>
                        </div>

                        <div class="flex items-end gap-2">
                            <Button @click="applyFilters" class="flex-1">
                                <Search class="mr-2 h-4 w-4" />
                                Buscar
                            </Button>
                            <Button @click="resetFilters" variant="outline">
                                <RefreshCw class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Tabla de Órdenes -->
            <Card>
                <CardHeader>
                    <CardTitle>Órdenes ({{ orders.total }})</CardTitle>
                    <CardDescription>
                        Mostrando {{ orders.from }} - {{ orders.to }} de {{ orders.total }} órdenes
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>ID</TableHead>
                                    <TableHead>Referencia</TableHead>
                                    <TableHead>Cliente</TableHead>
                                    <TableHead>Total</TableHead>
                                    <TableHead>Método de Pago</TableHead>
                                    <TableHead>Estado</TableHead>
                                    <TableHead>Fecha</TableHead>
                                    <TableHead class="text-right">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="orders.data.length === 0">
                                    <TableCell colspan="8" class="text-center py-8 text-muted-foreground">
                                        No se encontraron órdenes con los filtros seleccionados
                                    </TableCell>
                                </TableRow>
                                <TableRow v-for="order in orders.data" :key="order.id">
                                    <TableCell class="font-medium">#{{ order.id }}</TableCell>
                                    <TableCell>
                                        <code class="text-xs bg-muted px-1.5 py-0.5 rounded">
                                            {{ order.payment_reference || 'N/A' }}
                                        </code>
                                    </TableCell>
                                    <TableCell>
                                        <div>
                                            <p class="font-medium">{{ order.user?.name }}</p>
                                            <p class="text-xs text-muted-foreground">{{ order.user?.email }}</p>
                                        </div>
                                    </TableCell>
                                    <TableCell class="font-semibold">{{ formatCurrency(order.total) }}</TableCell>
                                    <TableCell>
                                        <span class="text-sm">{{ getPaymentMethodLabel(order.payment_method) }}</span>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="getStatusVariant(order.status)">
                                            {{ getStatusLabel(order.status) }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-sm text-muted-foreground">
                                        {{ formatDate(order.created_at) }}
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            @click="viewOrderDetail(order.id)"
                                        >
                                            <Eye class="mr-1 h-3 w-3" />
                                            Ver
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="totalPages > 1" class="flex items-center justify-between mt-4">
                        <p class="text-sm text-muted-foreground">
                            Página {{ currentPage }} de {{ totalPages }}
                        </p>
                        <div class="flex gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                :disabled="currentPage === 1"
                                @click="changePage(currentPage - 1)"
                            >
                                Anterior
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                :disabled="currentPage === totalPages"
                                @click="changePage(currentPage + 1)"
                            >
                                Siguiente
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
