<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { BreadcrumbItem, Order, OrderStatusHistory, Message } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Check, FileText, MapPin, Package, User, X, MessageCircle, Clock, Send, Image as ImageIcon, Paperclip } from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import Pusher from 'pusher-js';

interface Props {
    order: Order;
    chatStats?: {
        total_messages: number;
        unread_messages: number;
        last_message_at?: string | null;
    };
}

const props = defineProps<Props>();

const page = usePage();
const authUser = (page.props as any).auth?.user;

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
const activeTab = ref<'details' | 'timeline' | 'chat'>('details');
const chatMessages = ref<Message[]>(props.order.messages || []);
const chatMessageText = ref('');
const chatLoading = ref(false);
const pusherChannel = ref<any>(null);
const pusherConnected = ref(false);
const pusherError = ref<string | null>(null);

// Forms
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
    { value: 'received', label: 'Recibido' },
    { value: 'invoiced', label: 'Facturado' },
    { value: 'in_agency', label: 'En Agencia' },
    { value: 'on_the_way', label: 'En Camino' },
    { value: 'shipped', label: 'Enviado' },
    { value: 'delivered', label: 'Entregado' },
    { value: 'completed', label: 'Completado' },
    { value: 'cancelled', label: 'Cancelado' },
    { value: 'refunded', label: 'Reembolsado' },
];

const getStatusVariant = (status: Order['status']) => {
    const variants: Record<string, string> = {
        pending_payment: 'secondary',
        paid: 'default',
        received: 'default',
        invoiced: 'default',
        in_agency: 'default',
        on_the_way: 'default',
        shipped: 'default',
        delivered: 'default',
        completed: 'default',
        cancelled: 'destructive',
        refunded: 'outline',
    };
    return variants[status] || 'secondary';
};

const getStatusLabel = (status: Order['status']) => {
    const labels: Record<string, string> = {
        pending_payment: 'Pago Pendiente',
        paid: 'Pagado',
        received: 'Recibido',
        invoiced: 'Facturado',
        in_agency: 'En Agencia',
        on_the_way: 'En Camino',
        shipped: 'Enviado',
        delivered: 'Entregado',
        completed: 'Completado',
        cancelled: 'Cancelado',
        refunded: 'Reembolsado',
    };
    return labels[status] || status;
};

const getPaymentMethodLabel = (method: Order['payment_method']) => {
    const labels: Record<string, string> = {
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
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatTime = (date: string) => {
    return new Date(date).toLocaleTimeString('es-VE', {
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

// Timeline (solo historial)
const timeline = computed(() => {
    if (!props.order.status_history || props.order.status_history.length === 0) {
        return [];
    }
    
    // Ordenar por fecha ascendente
    return [...props.order.status_history].sort((a, b) => 
        new Date(a.created_at).getTime() - new Date(b.created_at).getTime()
    );
});

// Chat functions
const loadMessages = async () => {
    try {
        const response = await fetch(`/api/orders/${props.order.id}/chat`, {
            credentials: 'include', // Envía cookies de sesión
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        
        if (response.ok) {
            const data = await response.json();
            chatMessages.value = data.messages || [];
            await nextTick();
            scrollChatToBottom();
        } else if (response.status === 401) {
            console.error('No autenticado. Por favor inicia sesión.');
        }
    } catch (error) {
        console.error('Error loading messages:', error);
    }
};

const sendMessage = async () => {
    if (!chatMessageText.value.trim()) return;
    
    const currentText = chatMessageText.value;
    const tempId = Date.now();
    
    // Optimistic update: agregar mensaje inmediatamente
    const optimisticMessage: Message = {
        id: `temp-${tempId}`,
        order_id: props.order.id,
        user_id: authUser?.id || 0,
        message: currentText,
        message_type: 'text',
        file_path: null,
        is_delivery_message: false,
        is_read: false,
        created_at: new Date().toISOString(),
        user: authUser || undefined,
    } as Message;
    
    chatMessages.value.push(optimisticMessage);
    chatMessageText.value = '';
    await nextTick();
    scrollChatToBottom();
    
    const formData = new FormData();
    formData.append('message', currentText);
    
    try {
        const response = await fetch(`/api/orders/${props.order.id}/chat`, {
            method: 'POST',
            credentials: 'include', // Envía cookies de sesión
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                // No incluimos Content-Type, fetch lo establece automáticamente con boundary para FormData
            },
            body: formData,
        });
        
        if (response.ok) {
            const data = await response.json();
            // Reemplazar mensaje optimista con el real
            const optimisticIndex = chatMessages.value.findIndex(m => m.id === optimisticMessage.id);
            if (optimisticIndex !== -1 && data.data) {
                chatMessages.value[optimisticIndex] = data.data;
            } else if (data.data) {
                // Si no se encontró, agregar al final
                chatMessages.value.push(data.data);
            }
            await nextTick();
            scrollChatToBottom();
        } else {
            // Error: remover mensaje optimista
            const optimisticIndex = chatMessages.value.findIndex(m => m.id === optimisticMessage.id);
            if (optimisticIndex !== -1) {
                chatMessages.value.splice(optimisticIndex, 1);
            }
            chatMessageText.value = currentText; // Restaurar texto
            console.error('Error al enviar mensaje:', response.statusText);
        }
    } catch (error) {
        // Error: remover mensaje optimista
        const optimisticIndex = chatMessages.value.findIndex(m => m.id === optimisticMessage.id);
        if (optimisticIndex !== -1) {
            chatMessages.value.splice(optimisticIndex, 1);
        }
        chatMessageText.value = currentText; // Restaurar texto
        console.error('Error sending message:', error);
    }
};

const scrollChatToBottom = () => {
    const chatContainer = document.getElementById('chat-messages');
    if (chatContainer) {
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }
};

// Pusher setup
const setupPusher = () => {
    const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY || 'your-pusher-key';
    const pusherCluster = import.meta.env.VITE_PUSHER_CLUSTER || 'mt1';
    
    if (!pusherKey || pusherKey === 'your-pusher-key') {
        console.warn('Pusher key not configured');
        pusherError.value = 'Pusher no configurado';
        return;
    }
    
    const pusher = new Pusher(pusherKey, {
        cluster: pusherCluster,
        encrypted: true,
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
        // Habilitar envío de cookies (session)
        enabledTransports: ['ws', 'wss'],
    });
    
    const channel = pusher.subscribe(`private-order.${props.order.id}`);
    pusherChannel.value = channel;
    
    channel.bind('order.message.sent', (data: any) => {
        if (data.message) {
            // Evitar duplicados: verificar si el mensaje ya existe
            const messageExists = chatMessages.value.some(m => {
                // Comparar por ID real o por contenido si es mensaje optimista
                return m.id === data.message.id || 
                       (String(m.id).startsWith('temp-') && 
                        m.message === data.message.message && 
                        m.user_id === data.message.user_id);
            });
            
            if (!messageExists) {
                chatMessages.value.push(data.message);
                nextTick(() => scrollChatToBottom());
            } else {
                // Si existe un mensaje optimista, reemplazarlo
                const optimisticIndex = chatMessages.value.findIndex(m => 
                    String(m.id).startsWith('temp-') && 
                    m.message === data.message.message &&
                    m.user_id === data.message.user_id
                );
                if (optimisticIndex !== -1) {
                    chatMessages.value[optimisticIndex] = data.message;
                    nextTick(() => scrollChatToBottom());
                }
            }
        }
    });
    
    pusher.connection.bind('connected', () => {
        console.log('Conectado a Pusher');
        pusherConnected.value = true;
        pusherError.value = null;
    });

    pusher.connection.bind('disconnected', () => {
        console.log('Desconectado de Pusher');
        pusherConnected.value = false;
    });
    
    pusher.connection.bind('error', (err: any) => {
        console.error('Error de conexión Pusher:', err);
        pusherError.value = err?.error?.data?.message || 'Error de conexión';
        pusherConnected.value = false;
    });
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

// Lifecycle
onMounted(() => {
    loadMessages();
    setupPusher();
    watch(() => activeTab.value, (newTab) => {
        if (newTab === 'chat') {
            nextTick(() => {
                scrollChatToBottom();
            });
        }
    });
});

onUnmounted(() => {
    if (pusherChannel.value) {
        pusherChannel.value.unbind('order.message.sent');
        // Pusher disconnect will happen automatically
    }
});
</script>

<template>
    <Head :title="`Orden #${order.id} - Admin`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <Button variant="outline" size="sm" @click="goBack">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        <span class="hidden sm:inline">Volver</span>
                    </Button>
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold tracking-tight">Orden #{{ order.id }}</h1>
                        <p class="text-sm text-muted-foreground">
                            Creada el {{ formatDate(order.created_at) }}
                        </p>
                    </div>
                </div>
                <Badge :variant="getStatusVariant(order.status)" class="text-sm md:text-base px-3 py-1">
                    {{ getStatusLabel(order.status) }}
                </Badge>
            </div>

            <!-- Tabs -->
            <div class="border-b">
                <nav class="flex space-x-4 overflow-x-auto">
                    <button
                        @click="activeTab = 'details'"
                        :class="[
                            'px-4 py-2 text-sm font-medium transition-colors border-b-2 whitespace-nowrap',
                            activeTab === 'details'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground hover:border-muted-foreground'
                        ]"
                    >
                        Detalles
                    </button>
                    <button
                        @click="activeTab = 'timeline'"
                        :class="[
                            'px-4 py-2 text-sm font-medium transition-colors border-b-2 whitespace-nowrap',
                            activeTab === 'timeline'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground hover:border-muted-foreground'
                        ]"
                    >
                        <Clock class="inline-block mr-2 h-4 w-4" />
                        Timeline
                    </button>
                    <button
                        @click="activeTab = 'chat'"
                        :class="[
                            'px-4 py-2 text-sm font-medium transition-colors border-b-2 whitespace-nowrap relative',
                            activeTab === 'chat'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground hover:border-muted-foreground'
                        ]"
                    >
                        <MessageCircle class="inline-block mr-2 h-4 w-4" />
                        Chat
                        <span
                            v-if="chatStats?.unread_messages && chatStats.unread_messages > 0"
                            class="ml-2 px-2 py-0.5 text-xs rounded-full bg-primary text-primary-foreground"
                        >
                            {{ chatStats.unread_messages }}
                        </span>
                    </button>
                </nav>
            </div>

            <!-- Tab Content -->
            <div class="flex-1 overflow-auto">
                <!-- Details Tab -->
                <div v-show="activeTab === 'details'" class="grid gap-6 md:grid-cols-3">
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
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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

                        <!-- Delivery Info -->
                        <Card v-if="order.delivery">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Package class="h-5 w-5" />
                                    Delivery Asignado
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-2">
                                    <p class="font-medium">{{ order.delivery.name }}</p>
                                    <p class="text-sm text-muted-foreground">{{ order.delivery.email }}</p>
                                    <p v-if="order.delivery.tel" class="text-sm text-muted-foreground">Tel: {{ order.delivery.tel }}</p>
                                    <p v-if="order.assigned_at" class="text-xs text-muted-foreground">
                                        Asignado el {{ formatDate(order.assigned_at) }}
                                    </p>
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
                                    <code class="text-sm bg-muted px-2 py-1 rounded block break-all">
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
                    </div>
                </div>

                <!-- Timeline Tab -->
                <div v-show="activeTab === 'timeline'" class="max-w-3xl">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Clock class="h-5 w-5" />
                                Historial de Estados
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="relative">
                                <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-border"></div>
                                <div class="space-y-6">
                                    <div
                                        v-for="(item, index) in timeline"
                                        :key="item.id || index"
                                        class="relative pl-12"
                                    >
                                        <div class="absolute left-0 top-1.5 h-3 w-3 rounded-full bg-primary border-2 border-background"></div>
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-2">
                                                <Badge :variant="getStatusVariant(item.status as any)">
                                                    {{ item.status_label }}
                                                </Badge>
                                                <span class="text-xs text-muted-foreground">{{ formatDate(item.created_at) }}</span>
                                            </div>
                                            <p v-if="item.comment" class="text-sm text-muted-foreground">{{ item.comment }}</p>
                                            <p v-if="item.changed_by" class="text-xs text-muted-foreground">
                                                Por: {{ item.changed_by.name }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Chat Tab -->
                <div v-show="activeTab === 'chat'" class="flex flex-col h-[calc(100vh-250px)] max-w-4xl mx-auto">
                    <Card class="flex-1 flex flex-col">
                        <CardHeader>
                            <CardTitle class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <MessageCircle class="h-5 w-5" />
                                    Chat de la Orden
                                </div>
                                <div class="flex items-center gap-2 text-xs">
                                    <span
                                        :class="[
                                            'h-2 w-2 rounded-full',
                                            pusherConnected ? 'bg-green-500' : 'bg-gray-400'
                                        ]"
                                        :title="pusherConnected ? 'Conectado' : 'Desconectado'"
                                    ></span>
                                    <span v-if="pusherError" class="text-destructive text-xs">
                                        {{ pusherError }}
                                    </span>
                                </div>
                            </CardTitle>
                            <CardDescription>
                                {{ chatStats?.total_messages || 0 }} mensajes
                                <span v-if="chatStats?.unread_messages && chatStats.unread_messages > 0" class="text-primary">
                                    • {{ chatStats.unread_messages }} no leídos
                                </span>
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="flex-1 flex flex-col p-0">
                            <!-- Messages Area -->
                            <div id="chat-messages" class="flex-1 p-4 overflow-y-auto">
                                <div v-if="chatMessages.length === 0" class="flex items-center justify-center h-full text-muted-foreground">
                                    <div class="text-center">
                                        <MessageCircle class="h-12 w-12 mx-auto mb-2 opacity-50" />
                                        <p>No hay mensajes todavía</p>
                                    </div>
                                </div>
                                <div v-else class="space-y-4">
                                    <div
                                        v-for="message in chatMessages"
                                        :key="message.id"
                                        :class="[
                                            'flex gap-3',
                                            message.user_id === authUser?.id ? 'flex-row-reverse' : ''
                                        ]"
                                    >
                                        <div
                                            :class="[
                                                'flex flex-col gap-1 max-w-[80%] sm:max-w-[70%]',
                                                message.user_id === authUser?.id ? 'items-end' : 'items-start'
                                            ]"
                                        >
                                            <div
                                                :class="[
                                                    'rounded-lg px-4 py-2',
                                                    message.user_id === authUser?.id
                                                        ? 'bg-primary text-primary-foreground'
                                                        : 'bg-muted'
                                                ]"
                                            >
                                                <p class="text-sm">{{ message.message }}</p>
                                                <div v-if="message.message_type === 'image' && message.file_path" class="mt-2">
                                                    <img
                                                        :src="`/storage/${message.file_path}`"
                                                        alt="Imagen adjunta"
                                                        class="max-w-full rounded"
                                                    />
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                                <span>{{ message.user?.name || 'Usuario' }}</span>
                                                <span>•</span>
                                                <span>{{ formatTime(message.created_at) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Input Area -->
                            <div class="border-t p-4">
                                <form @submit.prevent="sendMessage" class="flex gap-2">
                                    <Input
                                        v-model="chatMessageText"
                                        placeholder="Escribe un mensaje..."
                                        class="flex-1"
                                        :disabled="chatLoading"
                                    />
                                    <Button type="submit" :disabled="chatLoading || !chatMessageText.trim()">
                                        <Send class="h-4 w-4" />
                                    </Button>
                                </form>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <!-- Dialog: Actualizar Estado -->
        <Dialog v-model:open="showStatusDialog">
            <DialogContent class="sm:max-w-md">
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
            <DialogContent class="sm:max-w-md">
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
