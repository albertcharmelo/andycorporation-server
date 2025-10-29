<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { BreadcrumbItem, Order } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Check, FileText, MapPin, Package, User, X } from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    order: Order;
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
    {
        title: `Orden #${props.order.id}`,
        href: `/admin/orders/${props.order.id}`,
    },
];

const showStatusDialog = ref(false);
const showNotesDialog = ref(false);
const showPaymentProof = ref(false);
const paymentProofUrl = ref('');

const statusForm = useForm({
    status: props.order.status,
    notes: '',
});

const notesForm = useForm({
    notes: '',
});

const statusOptions = [
    { value: 'pending_payment', label: 'Pago Pendiente' },
    { value: 'paid', label: 'Pagado' },
    { value: 'shipped', label: 'Enviado' },
    { value: 'completed', label: 'Completado' },
    { value: 'cancelled', label: 'Cancelado' },
    { value: 'refunded', label: 'Reembolsado' },
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
        manual_transfer: 'Transferencia Manual',
        mobile_payment: 'Pago Móvil',
        credit_card: 'Tarjeta de Crédito',
        paypal: 'PayPal',
        binance: 'Binance',
    };
    return labels[method] || method;
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('es-VE', {
        year: 'numeric',
        month: 'long',
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

const goBack = () => {
    router.visit('/admin/orders');
};

const updateStatus = () => {
    statusForm.put(`/admin/orders/${props.order.id}/status`, {
        preserveScroll: true,
        onSuccess: () => {
            showStatusDialog.value = false;
            statusForm.reset('notes');
        },
    });
};

const addNotes = () => {
    notesForm.put(`/admin/orders/${props.order.id}/notes`, {
        preserveScroll: true,
        onSuccess: () => {
            showNotesDialog.value = false;
            notesForm.reset();
        },
    });
};

const viewPaymentProof = async () => {
    try {
        const response = await fetch(`/api/admin/orders/${props.order.id}/payment-proof`, {
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('api_token')}`,
                'Accept': 'application/json',
            },
        });

        if (response.ok) {
            const data = await response.json();
            paymentProofUrl.value = data.data.url;
            showPaymentProof.value = true;
        }
    } catch (error) {
        console.error('Error loading payment proof:', error);
    }
};
</script>

<template>
    <Head :title="`Orden #${order.id} - Admin`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button variant="outline" size="sm" @click="goBack">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Volver
                    </Button>
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight">Orden #{{ order.id }}</h1>
                        <p class="text-sm text-muted-foreground">
                            Creada el {{ formatDate(order.created_at) }}
                        </p>
                    </div>
                </div>
                <Badge :variant="getStatusVariant(order.status)" class="text-base px-3 py-1">
                    {{ getStatusLabel(order.status) }}
                </Badge>
            </div>

            <div class="grid gap-6 md:grid-cols-3">
                <!-- Columna Izquierda: Detalles -->
                <div class="md:col-span-2 space-y-6">
                    <!-- Información del Cliente -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <User class="h-5 w-5" />
                                Información del Cliente
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm font-medium text-muted-foreground">Nombre</p>
                                    <p class="text-base">{{ order.user?.name }}</p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-muted-foreground">Email</p>
                                    <p class="text-base">{{ order.user?.email }}</p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-muted-foreground">Teléfono</p>
                                    <p class="text-base">{{ order.user?.tel || 'No proporcionado' }}</p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-muted-foreground">Cédula</p>
                                    <p class="text-base">
                                        {{ order.user?.cedula_type?.toUpperCase() }}-{{ order.user?.cedula_ID || 'N/A' }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Dirección de Envío -->
                    <Card v-if="order.address">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <MapPin class="h-5 w-5" />
                                Dirección de Envío
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2">
                            <p class="font-medium">{{ order.address.name }}</p>
                            <p class="text-sm">{{ order.address.address_line_1 }}</p>
                            <p v-if="order.address.address_line_2" class="text-sm">{{ order.address.address_line_2 }}</p>
                            <p class="text-sm">Código Postal: {{ order.address.postal_code }}</p>
                            <p v-if="order.address.referencia" class="text-sm text-muted-foreground">
                                Referencia: {{ order.address.referencia }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Coordenadas: {{ order.address.latitude }}, {{ order.address.longitude }}
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Items de la Orden -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Package class="h-5 w-5" />
                                Productos ({{ order.items?.length || 0 }})
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <div
                                    v-for="item in order.items"
                                    :key="item.id"
                                    class="flex items-center justify-between border-b pb-3 last:border-0"
                                >
                                    <div class="flex-1">
                                        <p class="font-medium">{{ item.product_name }}</p>
                                        <p class="text-sm text-muted-foreground">
                                            Cantidad: {{ item.quantity }} × {{ formatCurrency(item.price_at_purchase) }}
                                        </p>
                                    </div>
                                    <p class="font-semibold">
                                        {{ formatCurrency(Number(item.price_at_purchase) * item.quantity) }}
                                    </p>
                                </div>

                                <div class="space-y-2 pt-4 border-t">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground">Subtotal</span>
                                        <span>{{ formatCurrency(order.subtotal) }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground">Envío</span>
                                        <span>{{ formatCurrency(order.shipping_cost) }}</span>
                                    </div>
                                    <div class="flex justify-between text-lg font-bold">
                                        <span>Total</span>
                                        <span>{{ formatCurrency(order.total) }}</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Notas -->
                    <Card v-if="order.notes">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <FileText class="h-5 w-5" />
                                Notas
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <pre class="whitespace-pre-wrap text-sm text-muted-foreground">{{ order.notes }}</pre>
                        </CardContent>
                    </Card>
                </div>

                <!-- Columna Derecha: Acciones -->
                <div class="space-y-6">
                    <!-- Información de Pago -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Información de Pago</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Método de Pago</p>
                                <p class="text-base">{{ getPaymentMethodLabel(order.payment_method) }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Referencia</p>
                                <code class="text-sm bg-muted px-2 py-1 rounded">
                                    {{ order.payment_reference || 'N/A' }}
                                </code>
                            </div>
                            <div v-if="order.payment_proof">
                                <Button variant="outline" class="w-full" @click="viewPaymentProof">
                                    <FileText class="mr-2 h-4 w-4" />
                                    Ver Comprobante
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Acciones -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Acciones</CardTitle>
                            <CardDescription>Gestiona el estado de esta orden</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <Button class="w-full" @click="showStatusDialog = true">
                                <Check class="mr-2 h-4 w-4" />
                                Actualizar Estado
                            </Button>
                            <Button variant="outline" class="w-full" @click="showNotesDialog = true">
                                <FileText class="mr-2 h-4 w-4" />
                                Agregar Notas
                            </Button>
                        </CardContent>
                    </Card>

                    <!-- Historial -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Historial</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2 text-sm">
                            <div>
                                <p class="text-muted-foreground">Creada</p>
                                <p>{{ formatDate(order.created_at) }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Última actualización</p>
                                <p>{{ formatDate(order.updated_at) }}</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <!-- Dialog: Actualizar Estado -->
        <Dialog v-model:open="showStatusDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Actualizar Estado de la Orden</DialogTitle>
                    <DialogDescription>
                        Cambia el estado de la orden #{{ order.id }}
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="updateStatus" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="status">Nuevo Estado</Label>
                        <Select v-model="statusForm.status">
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
                        <p v-if="statusForm.errors.status" class="text-sm text-destructive">
                            {{ statusForm.errors.status }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="status-notes">Notas (opcional)</Label>
                        <Textarea
                            id="status-notes"
                            v-model="statusForm.notes"
                            placeholder="Agrega notas sobre este cambio de estado..."
                            rows="3"
                        />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showStatusDialog = false">
                            Cancelar
                        </Button>
                        <Button type="submit" :disabled="statusForm.processing">
                            {{ statusForm.processing ? 'Actualizando...' : 'Actualizar' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Dialog: Agregar Notas -->
        <Dialog v-model:open="showNotesDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Agregar Notas</DialogTitle>
                    <DialogDescription>
                        Agrega notas internas sobre esta orden
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="addNotes" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="notes">Notas</Label>
                        <Textarea
                            id="notes"
                            v-model="notesForm.notes"
                            placeholder="Escribe tus notas aquí..."
                            rows="5"
                            required
                        />
                        <p v-if="notesForm.errors.notes" class="text-sm text-destructive">
                            {{ notesForm.errors.notes }}
                        </p>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showNotesDialog = false">
                            Cancelar
                        </Button>
                        <Button type="submit" :disabled="notesForm.processing">
                            {{ notesForm.processing ? 'Guardando...' : 'Guardar' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Dialog: Ver Comprobante de Pago -->
        <Dialog v-model:open="showPaymentProof">
            <DialogContent class="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Comprobante de Pago</DialogTitle>
                    <DialogDescription>
                        Orden #{{ order.id }} - {{ order.payment_reference }}
                    </DialogDescription>
                </DialogHeader>

                <div class="flex justify-center items-center">
                    <img
                        :src="paymentProofUrl"
                        alt="Comprobante de pago"
                        class="max-w-full max-h-[70vh] object-contain rounded-lg border"
                    />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showPaymentProof = false">
                        Cerrar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
