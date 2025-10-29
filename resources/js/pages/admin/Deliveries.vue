<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Package, Plus, Trash2, Truck, UserPlus } from 'lucide-vue-next';
import { ref } from 'vue';

interface Delivery {
    id: number;
    name: string;
    email: string;
    tel: string;
    active_orders: number;
    total_deliveries: number;
    created_at: string;
}

interface Props {
    deliveries: Delivery[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Admin',
        href: '/admin/dashboard',
    },
    {
        title: 'Deliveries',
        href: '/admin/deliveries',
    },
];

const showCreateDialog = ref(false);

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    tel: '',
    cedula_type: 'v',
    cedula_ID: '',
});

const cedulaTypes = [
    { value: 'v', label: 'V - Venezolano' },
    { value: 'j', label: 'J - Jurídico' },
    { value: 'e', label: 'E - Extranjero' },
    { value: 'g', label: 'G - Gubernamental' },
    { value: 'r', label: 'R - RIF' },
    { value: 'p', label: 'P - Pasaporte' },
];

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('es-VE', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const createDelivery = async () => {
    try {
        const response = await fetch('/api/admin/deliveries', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('api_token')}`,
            },
            body: JSON.stringify(createForm.data()),
        });

        const data = await response.json();

        if (data.success) {
            showCreateDialog.value = false;
            createForm.reset();
            router.reload();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error creating delivery:', error);
        alert('Error al crear el delivery');
    }
};

const viewOrders = (deliveryId: number) => {
    router.visit(`/admin/deliveries/${deliveryId}/orders`);
};

const deleteDelivery = async (deliveryId: number, deliveryName: string) => {
    if (!confirm(`¿Estás seguro de eliminar a ${deliveryName}?`)) {
        return;
    }

    try {
        const response = await fetch(`/api/admin/deliveries/${deliveryId}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('api_token')}`,
            },
        });

        const data = await response.json();

        if (data.success) {
            router.reload();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error deleting delivery:', error);
        alert('Error al eliminar el delivery');
    }
};
</script>

<template>
    <Head title="Gestión de Deliveries - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Gestión de Deliveries</h1>
                    <p class="text-sm text-muted-foreground">
                        Administra los repartidores del sistema
                    </p>
                </div>
                <Button @click="showCreateDialog = true" style="background-color: #0b3c87; color: white;">
                    <UserPlus class="mr-2 h-4 w-4" />
                    Crear Delivery
                </Button>
            </div>

            <!-- Estadísticas -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card class="border-l-4" style="border-left-color: #0b3c87;">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">
                            Total Deliveries
                        </CardTitle>
                        <div class="p-2 rounded-lg" style="background-color: #0b3c87;">
                            <Truck class="h-4 w-4 text-white" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold" style="color: #0b3c87;">
                            {{ deliveries.length }}
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">
                            Repartidores activos
                        </p>
                    </CardContent>
                </Card>

                <Card class="border-l-4 border-l-orange-500">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">
                            Órdenes Activas
                        </CardTitle>
                        <div class="p-2 rounded-lg bg-orange-500">
                            <Package class="h-4 w-4 text-white" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-orange-600">
                            {{ deliveries.reduce((sum, d) => sum + d.active_orders, 0) }}
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">
                            En tránsito
                        </p>
                    </CardContent>
                </Card>

                <Card class="border-l-4 border-l-green-500">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">
                            Total Entregas
                        </CardTitle>
                        <div class="p-2 rounded-lg bg-green-500">
                            <Package class="h-4 w-4 text-white" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-green-600">
                            {{ deliveries.reduce((sum, d) => sum + d.total_deliveries, 0) }}
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">
                            Completadas
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Tabla de Deliveries -->
            <Card>
                <CardHeader>
                    <CardTitle>Listado de Deliveries</CardTitle>
                    <CardDescription>
                        Gestiona los repartidores y ve sus estadísticas
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="deliveries.length === 0" class="text-center py-12 text-muted-foreground">
                        <Truck class="mx-auto h-12 w-12 mb-4 opacity-50" />
                        <p class="text-lg font-medium">No hay deliveries registrados</p>
                        <p class="text-sm">Crea tu primer delivery para comenzar</p>
                    </div>

                    <div v-else class="rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>ID</TableHead>
                                    <TableHead>Nombre</TableHead>
                                    <TableHead>Contacto</TableHead>
                                    <TableHead class="text-center">Órdenes Activas</TableHead>
                                    <TableHead class="text-center">Total Entregas</TableHead>
                                    <TableHead>Fecha Registro</TableHead>
                                    <TableHead class="text-right">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="delivery in deliveries" :key="delivery.id">
                                    <TableCell class="font-medium">#{{ delivery.id }}</TableCell>
                                    <TableCell>
                                        <div class="flex items-center gap-2">
                                            <div class="h-8 w-8 rounded-full flex items-center justify-center text-white font-semibold" style="background-color: #0b3c87;">
                                                {{ delivery.name.charAt(0).toUpperCase() }}
                                            </div>
                                            <span class="font-medium">{{ delivery.name }}</span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div>
                                            <p class="text-sm">{{ delivery.email }}</p>
                                            <p class="text-xs text-muted-foreground">{{ delivery.tel }}</p>
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-center">
                                        <Badge v-if="delivery.active_orders > 0" variant="default">
                                            {{ delivery.active_orders }}
                                        </Badge>
                                        <span v-else class="text-muted-foreground">0</span>
                                    </TableCell>
                                    <TableCell class="text-center">
                                        <span class="font-semibold" style="color: #0b3c87;">
                                            {{ delivery.total_deliveries }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="text-sm text-muted-foreground">
                                        {{ formatDate(delivery.created_at) }}
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                @click="viewOrders(delivery.id)"
                                            >
                                                <Package class="mr-1 h-3 w-3" />
                                                Ver Órdenes
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                                @click="deleteDelivery(delivery.id, delivery.name)"
                                                :disabled="delivery.active_orders > 0"
                                            >
                                                <Trash2 class="h-3 w-3" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Dialog: Crear Delivery -->
        <Dialog v-model:open="showCreateDialog">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Crear Nuevo Delivery</DialogTitle>
                    <DialogDescription>
                        Registra un nuevo repartidor en el sistema
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="createDelivery" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="name">Nombre Completo</Label>
                        <Input
                            id="name"
                            v-model="createForm.name"
                            placeholder="Juan Pérez"
                            required
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="email">Email</Label>
                        <Input
                            id="email"
                            v-model="createForm.email"
                            type="email"
                            placeholder="juan@delivery.com"
                            required
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="password">Contraseña</Label>
                        <Input
                            id="password"
                            v-model="createForm.password"
                            type="password"
                            placeholder="Mínimo 6 caracteres"
                            required
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="tel">Teléfono</Label>
                        <Input
                            id="tel"
                            v-model="createForm.tel"
                            placeholder="04141234567"
                            required
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="cedula-type">Tipo de Cédula</Label>
                            <Select v-model="createForm.cedula_type">
                                <SelectTrigger id="cedula-type">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="type in cedulaTypes"
                                        :key="type.value"
                                        :value="type.value"
                                    >
                                        {{ type.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <Label for="cedula-id">Número de Cédula</Label>
                            <Input
                                id="cedula-id"
                                v-model="createForm.cedula_ID"
                                placeholder="12345678"
                                required
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showCreateDialog = false">
                            Cancelar
                        </Button>
                        <Button type="submit" style="background-color: #0b3c87; color: white;">
                            <Plus class="mr-2 h-4 w-4" />
                            Crear Delivery
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
