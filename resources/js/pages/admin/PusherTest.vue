<template>
    <AppLayout>
        <Head title="Prueba de Pusher" />
        <div class="container mx-auto p-6">
        <div class="mb-6">
            <h1 class="text-3xl font-bold mb-2">Prueba de Eventos Pusher</h1>
            <p class="text-muted-foreground">Verifica que los eventos se disparen y se reciban correctamente</p>
        </div>

        <!-- Estado de Conexión -->
        <Card class="mb-6">
            <CardHeader>
                <CardTitle>Estado de Conexión</CardTitle>
            </CardHeader>
            <CardContent>
            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <div :class="[
                        'w-3 h-3 rounded-full',
                        pusherConnected ? 'bg-green-500' : 'bg-red-500'
                    ]"></div>
                    <span>Pusher: {{ pusherConnected ? 'Conectado' : 'Desconectado' }}</span>
                </div>
                <div v-if="pusherError" class="text-destructive text-sm">
                    Error: {{ pusherError }}
                </div>
            </div>
            </CardContent>
        </Card>

        <!-- Configuración de Prueba -->
        <Card class="mb-6">
            <CardHeader>
                <CardTitle>Enviar Mensaje de Prueba</CardTitle>
            </CardHeader>
            <CardContent>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">ID de Orden</label>
                    <Input
                        v-model="testOrderId"
                        type="number"
                        placeholder="Ej: 1"
                        class="w-full"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Mensaje de Prueba</label>
                    <Input
                        v-model="testMessage"
                        placeholder="Escribe un mensaje de prueba..."
                        class="w-full"
                    />
                </div>
                <Button
                    @click="sendTestMessage"
                    :disabled="!testOrderId || !testMessage || sendingTest"
                >
                    {{ sendingTest ? 'Enviando...' : 'Enviar Mensaje de Prueba' }}
                </Button>
            </div>
            </CardContent>
        </Card>

        <!-- Canales Suscritos -->
        <Card class="mb-6">
            <CardHeader>
                <CardTitle>Canales Suscritos</CardTitle>
            </CardHeader>
            <CardContent>
            <div class="space-y-2">
                <div
                    v-for="channel in subscribedChannels"
                    :key="channel"
                    class="flex items-center justify-between p-2 bg-muted rounded"
                >
                    <span class="font-mono text-sm">{{ channel }}</span>
                    <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                </div>
                <div v-if="subscribedChannels.length === 0" class="text-muted-foreground text-sm">
                    No hay canales suscritos
                </div>
            </div>
            </CardContent>
        </Card>

        <!-- Eventos Recibidos -->
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle>Eventos Recibidos</CardTitle>
                    <Button variant="outline" size="sm" @click="clearEvents">
                        Limpiar
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <div class="space-y-2 max-h-96 overflow-y-auto">
                <div
                    v-for="(event, index) in receivedEvents"
                    :key="index"
                    class="p-3 bg-muted rounded border-l-4"
                    :class="{
                        'border-green-500': event.type === 'order.message.sent',
                        'border-blue-500': event.type === 'pusher:subscription_succeeded',
                        'border-red-500': event.type === 'pusher:subscription_error',
                        'border-gray-500': !event.type || event.type === 'other'
                    }"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-semibold text-sm">{{ event.eventName }}</span>
                        <span class="text-xs text-muted-foreground">{{ event.timestamp }}</span>
                    </div>
                    <div class="text-xs text-muted-foreground mb-1">
                        Canal: {{ event.channel }}
                    </div>
                    <pre class="text-xs bg-background p-2 rounded overflow-x-auto">{{ JSON.stringify(event.data, null, 2) }}</pre>
                </div>
                <div v-if="receivedEvents.length === 0" class="text-muted-foreground text-sm text-center py-8">
                    No se han recibido eventos aún
                </div>
                </div>
            </CardContent>
        </Card>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, onMounted, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Head } from '@inertiajs/vue3';
import Pusher from 'pusher-js';

const pusherConnected = ref(false);
const pusherError = ref<string | null>(null);
const subscribedChannels = ref<string[]>([]);
const receivedEvents = ref<any[]>([]);
const testOrderId = ref<number | null>(null);
const testMessage = ref('');
const sendingTest = ref(false);

let pusherInstance: any = null;
const channels = new Map<string, any>();

const setupPusher = () => {
    const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY || 'your-pusher-key';
    const pusherCluster = import.meta.env.VITE_PUSHER_CLUSTER || 'mt1';
    
    if (!pusherKey || pusherKey === 'your-pusher-key') {
        pusherError.value = 'Pusher key no configurada';
        return;
    }
    
    const apiToken = localStorage.getItem('api_token');
    const authHeaders: any = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    
    if (apiToken) {
        authHeaders['Authorization'] = `Bearer ${apiToken}`;
    }
    
    pusherInstance = new Pusher(pusherKey, {
        cluster: pusherCluster,
        encrypted: true,
        authEndpoint: apiToken ? '/api/broadcasting/auth' : '/broadcasting/auth',
        auth: {
            headers: authHeaders,
        },
        enabledTransports: ['ws', 'wss'],
        forceTLS: false,
    });
    
    pusherInstance.connection.bind('connected', () => {
        console.log('[PusherTest] ✅ Conectado a Pusher');
        pusherConnected.value = true;
        pusherError.value = null;
    });

    pusherInstance.connection.bind('disconnected', () => {
        console.log('[PusherTest] Desconectado de Pusher');
        pusherConnected.value = false;
    });
    
    pusherInstance.connection.bind('error', (err: any) => {
        console.error('[PusherTest] Error de conexión:', err);
        pusherError.value = err?.error?.data?.message || 'Error de conexión Pusher';
        pusherConnected.value = false;
    });
};

const subscribeToChannel = (channelName: string) => {
    if (!pusherInstance || !pusherConnected.value) {
        console.warn('[PusherTest] Pusher no está conectado');
        return;
    }
    
    if (channels.has(channelName)) {
        console.log('[PusherTest] Ya está suscrito a:', channelName);
        return;
    }
    
    try {
        const channel = pusherInstance.subscribe(channelName);
        channels.set(channelName, channel);
        
        // Actualizar lista de canales suscritos
        subscribedChannels.value = Array.from(channels.keys());
        
        // Manejar eventos de suscripción
        channel.bind('pusher:subscription_error', (status: number) => {
            console.error('[PusherTest] ❌ Error de suscripción:', status);
            addEvent('pusher:subscription_error', channelName, { status, error: 'Error de autenticación' });
            pusherError.value = `Error de autenticación (${status})`;
        });
        
        channel.bind('pusher:subscription_succeeded', () => {
            console.log('[PusherTest] ✅ Suscrito exitosamente a:', channelName);
            addEvent('pusher:subscription_succeeded', channelName, { message: 'Suscripción exitosa' });
        });
        
        // Escuchar todos los eventos del canal
        channel.bind_global((eventName: string, data: any) => {
            console.log('[PusherTest] Evento recibido:', eventName, data);
            addEvent(eventName, channelName, data);
        });
        
        // Escuchar específicamente el evento de mensaje
        channel.bind('order.message.sent', (data: any) => {
            console.log('[PusherTest] ✅ Evento order.message.sent recibido:', data);
            addEvent('order.message.sent', channelName, data);
        });
        
    } catch (error) {
        console.error('[PusherTest] Error al suscribirse:', error);
        pusherError.value = 'Error al suscribirse al canal';
    }
};

const addEvent = (eventName: string, channel: string, data: any) => {
    receivedEvents.value.unshift({
        eventName,
        channel,
        data,
        timestamp: new Date().toLocaleTimeString(),
        type: eventName.includes('message') ? 'order.message.sent' : 
              eventName.includes('subscription_succeeded') ? 'pusher:subscription_succeeded' :
              eventName.includes('subscription_error') ? 'pusher:subscription_error' : 'other'
    });
    
    // Limitar a 50 eventos
    if (receivedEvents.value.length > 50) {
        receivedEvents.value = receivedEvents.value.slice(0, 50);
    }
};

const sendTestMessage = async () => {
    if (!testOrderId.value || !testMessage.value) {
        return;
    }
    
    sendingTest.value = true;
    
    try {
        // Suscribirse al canal si no está suscrito
        const channelName = `private-order.${testOrderId.value}`;
        if (!channels.has(channelName)) {
            subscribeToChannel(channelName);
        }
        
        // Enviar mensaje de prueba usando FormData (como espera el controlador)
        const formData = new FormData();
        formData.append('message', testMessage.value);
        
        const response = await fetch(`/api/orders/${testOrderId.value}/chat`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                // NO incluir Content-Type, el navegador lo establecerá automáticamente con el boundary para FormData
            },
            body: formData,
            credentials: 'include', // Importante para enviar cookies
        });
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.message || 'Error al enviar mensaje');
        }
        
        const result = await response.json();
        console.log('[PusherTest] Mensaje enviado:', result);
        
        // Limpiar el campo de mensaje
        testMessage.value = '';
        
    } catch (error: any) {
        console.error('[PusherTest] Error:', error);
        pusherError.value = error.message || 'Error al enviar mensaje';
    } finally {
        sendingTest.value = false;
    }
};

const clearEvents = () => {
    receivedEvents.value = [];
};

onMounted(() => {
    setupPusher();
});

onUnmounted(() => {
    if (pusherInstance) {
        // Desuscribirse de todos los canales
        channels.forEach((channel, channelName) => {
            try {
                pusherInstance.unsubscribe(channelName);
            } catch (error) {
                console.error('[PusherTest] Error al desuscribirse:', error);
            }
        });
        channels.clear();
        subscribedChannels.value = [];
        
        pusherInstance.disconnect();
    }
});
</script>

