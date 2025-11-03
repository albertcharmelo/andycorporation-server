# Documentación API Chat - Frontend

## Descripción General

Sistema de chat en tiempo real para órdenes que permite comunicación entre Cliente, Admin y Delivery. Los mensajes se sincronizan automáticamente vía Pusher para una experiencia en tiempo real.

## Reglas de Acceso

- **Cliente**: Puede ver y enviar mensajes en sus propias órdenes
- **Admin**: Puede ver y enviar mensajes en todas las órdenes
- **Delivery**: Solo puede ver y enviar mensajes después de ser asignado a la orden. No ve mensajes anteriores a su asignación.

## Endpoints de Chat

### Base URL

```
https://tu-dominio.com/api
```

### Autenticación

Todos los endpoints requieren token Bearer en el header:

```
Authorization: Bearer {tu_token}
```

---

## 1. Obtener Mensajes del Chat

**Endpoint:** `GET /api/orders/{orderId}/chat`

**Descripción:** Obtiene todos los mensajes del chat de una orden. El delivery solo verá mensajes posteriores a su asignación.

**Parámetros:**

- `orderId` (path, requerido): ID de la orden

**Response (200 OK):**

```json
{
    "order_id": 1,
    "messages": [
        {
            "id": 1,
            "order_id": 1,
            "user_id": 1,
            "message": "Hola, tengo una pregunta sobre mi pedido",
            "message_type": "text",
            "file_path": null,
            "is_delivery_message": false,
            "is_read": true,
            "created_at": "2025-11-03T10:00:00.000000Z",
            "user": {
                "id": 1,
                "name": "Juan Pérez",
                "email": "juan@example.com",
                "avatar": null
            }
        },
        {
            "id": 2,
            "order_id": 1,
            "user_id": 2,
            "message": "Claro, ¿en qué puedo ayudarte?",
            "message_type": "text",
            "file_path": null,
            "is_delivery_message": false,
            "is_read": true,
            "created_at": "2025-11-03T10:05:00.000000Z",
            "user": {
                "id": 2,
                "name": "Admin",
                "email": "admin@example.com",
                "avatar": null
            }
        }
    ],
    "user_role": "client"
}
```

**user_role posibles:**

- `"client"` - Cliente de la orden
- `"admin"` - Administrador
- `"delivery"` - Delivery asignado

**Ejemplo en JavaScript/React Native:**

```javascript
const getMessages = async (orderId, token) => {
    try {
        const response = await fetch(`${API_BASE_URL}/orders/${orderId}/chat`, {
            method: 'GET',
            headers: {
                Authorization: `Bearer ${token}`,
                Accept: 'application/json',
            },
        });

        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error al obtener mensajes:', error);
        throw error;
    }
};
```

---

## 2. Enviar Mensaje

**Endpoint:** `POST /api/orders/{orderId}/chat`

**Descripción:** Envía un mensaje en el chat. Soporta texto y archivos adjuntos.

**Parámetros:**

- `orderId` (path, requerido): ID de la orden

**Body (Texto):**

```
Content-Type: multipart/form-data

message: "Hola, ¿cuándo llegará mi pedido?"
message_type: "text" (opcional, default: "text")
```

**Body (Con archivo/imagen):**

```
Content-Type: multipart/form-data

message: "Aquí está la foto del problema"
message_type: "image" o "file" (opcional, se detecta automáticamente)
file: [archivo]
```

**Response (201 Created):**

```json
{
    "message": "Mensaje enviado exitosamente",
    "data": {
        "id": 6,
        "order_id": 1,
        "user_id": 1,
        "message": "Hola, ¿cuándo llegará mi pedido?",
        "message_type": "text",
        "file_path": null,
        "is_delivery_message": false,
        "is_read": false,
        "created_at": "2025-11-03T12:00:00.000000Z",
        "user": {
            "id": 1,
            "name": "Juan Pérez",
            "email": "juan@example.com",
            "avatar": null
        }
    }
}
```

**Ejemplo en React Native (con archivo):**

```javascript
import * as ImagePicker from 'expo-image-picker';

const sendMessage = async (orderId, messageText, file = null, token) => {
    const formData = new FormData();
    formData.append('message', messageText);

    if (file) {
        formData.append('file', {
            uri: file.uri,
            type: file.type,
            name: file.name || 'image.jpg',
        });
    }

    try {
        const response = await fetch(`${API_BASE_URL}/orders/${orderId}/chat`, {
            method: 'POST',
            headers: {
                Authorization: `Bearer ${token}`,
                'Content-Type': 'multipart/form-data',
            },
            body: formData,
        });

        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error al enviar mensaje:', error);
        throw error;
    }
};

// Ejemplo de uso con imagen
const sendImage = async () => {
    const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ImagePicker.MediaTypeOptions.Images,
        allowsEditing: true,
        quality: 0.8,
    });

    if (!result.canceled) {
        await sendMessage(orderId, 'Aquí está la foto', result.assets[0], token);
    }
};
```

---

## 3. Marcar Mensajes como Leídos

**Endpoint:** `PUT /api/orders/{orderId}/chat/mark-read`

**Descripción:** Marca todos los mensajes no leídos de la orden como leídos.

**Response (200 OK):**

```json
{
    "message": "Mensajes marcados como leídos",
    "updated_count": 3
}
```

**Ejemplo:**

```javascript
const markAsRead = async (orderId, token) => {
    const response = await fetch(`${API_BASE_URL}/orders/${orderId}/chat/mark-read`, {
        method: 'PUT',
        headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
        },
    });

    return await response.json();
};
```

---

## 4. Obtener Estadísticas del Chat

**Endpoint:** `GET /api/orders/{orderId}/chat/stats`

**Descripción:** Obtiene estadísticas del chat (total de mensajes, no leídos, etc.).

**Response (200 OK):**

```json
{
    "stats": {
        "total_messages": 15,
        "unread_messages": 2,
        "delivery_messages": 5,
        "pre_delivery_messages": 10,
        "last_message_at": "2025-11-03T12:00:00.000000Z"
    }
}
```

---

## 5. Descargar Archivo Adjunto

**Endpoint:** `GET /api/orders/{orderId}/chat/attachment/{messageId}`

**Descripción:** Obtiene un archivo adjunto de un mensaje.

**Parámetros:**

- `orderId` (path, requerido): ID de la orden
- `messageId` (path, requerido): ID del mensaje

**Response:** Archivo binario con Content-Type apropiado

**Ejemplo en React Native:**

```javascript
const getAttachmentUrl = (orderId, messageId) => {
    return `${API_BASE_URL}/orders/${orderId}/chat/attachment/${messageId}?token=${token}`;
};
```

---

## Integración con Pusher (Tiempo Real)

### Instalación en Expo/React Native

```bash
npm install pusher-js
# o
yarn add pusher-js
```

### Configuración

```javascript
import Pusher from 'pusher-js';

// Configuración de Pusher
const pusher = new Pusher('TU_PUSHER_APP_KEY', {
    cluster: 'tu_cluster', // ej: 'us2', 'eu', 'ap1'
    encrypted: true,
    authEndpoint: `${API_BASE_URL}/broadcasting/auth`,
    auth: {
        headers: {
            Authorization: `Bearer ${userToken}`,
            Accept: 'application/json',
        },
    },
});
```

### Suscribirse a Canal Privado de Orden

```javascript
const subscribeToOrderChat = (orderId, token) => {
    // Nombre del canal: private-order.{orderId}
    const channel = pusher.subscribe(`private-order.${orderId}`);

    // Escuchar cuando se envía un nuevo mensaje
    channel.bind('order.message.sent', (data) => {
        console.log('Nuevo mensaje recibido:', data);

        // data contiene:
        // {
        //   order: { id, status, user_id, delivery_id },
        //   message: { id, message, message_type, user, ... },
        //   sender: { id, name, role }
        // }

        // Actualizar tu lista de mensajes
        addMessageToChat(data.message);

        // Mostrar notificación si el mensaje no es del usuario actual
        if (data.sender.id !== currentUserId) {
            showNotification(`Nuevo mensaje de ${data.sender.name}`);
        }
    });

    return channel;
};
```

### Ejemplo Completo de Hook React Native

```javascript
import { useEffect, useState } from 'react';
import Pusher from 'pusher-js';

const useOrderChat = (orderId, token) => {
    const [messages, setMessages] = useState([]);
    const [loading, setLoading] = useState(true);
    const [pusherChannel, setPusherChannel] = useState(null);

    // Cargar mensajes iniciales
    useEffect(() => {
        const loadMessages = async () => {
            try {
                const response = await fetch(`${API_BASE_URL}/orders/${orderId}/chat`, {
                    headers: {
                        Authorization: `Bearer ${token}`,
                        Accept: 'application/json',
                    },
                });
                const data = await response.json();
                setMessages(data.messages || []);
                setLoading(false);
            } catch (error) {
                console.error('Error al cargar mensajes:', error);
                setLoading(false);
            }
        };

        loadMessages();
    }, [orderId, token]);

    // Configurar Pusher
    useEffect(() => {
        if (!orderId || !token) return;

        // Inicializar Pusher
        const pusher = new Pusher('TU_PUSHER_APP_KEY', {
            cluster: 'tu_cluster',
            encrypted: true,
            authEndpoint: `${API_BASE_URL}/broadcasting/auth`,
            auth: {
                headers: {
                    Authorization: `Bearer ${token}`,
                    Accept: 'application/json',
                },
            },
        });

        // Suscribirse al canal de la orden
        const channel = pusher.subscribe(`private-order.${orderId}`);
        setPusherChannel(channel);

        // Escuchar nuevos mensajes
        channel.bind('order.message.sent', (data) => {
            setMessages((prev) => [...prev, data.message]);
        });

        // Manejo de errores de conexión
        pusher.connection.bind('error', (err) => {
            console.error('Error de conexión Pusher:', err);
        });

        pusher.connection.bind('connected', () => {
            console.log('Conectado a Pusher');
        });

        pusher.connection.bind('disconnected', () => {
            console.log('Desconectado de Pusher');
        });

        // Cleanup
        return () => {
            channel.unbind('order.message.sent');
            pusher.unsubscribe(`private-order.${orderId}`);
            pusher.disconnect();
        };
    }, [orderId, token]);

    // Función para enviar mensaje
    const sendMessage = async (messageText, file = null) => {
        const formData = new FormData();
        formData.append('message', messageText);

        if (file) {
            formData.append('file', {
                uri: file.uri,
                type: file.type,
                name: file.name || 'file.jpg',
            });
        }

        try {
            const response = await fetch(`${API_BASE_URL}/orders/${orderId}/chat`, {
                method: 'POST',
                headers: {
                    Authorization: `Bearer ${token}`,
                    'Content-Type': 'multipart/form-data',
                },
                body: formData,
            });

            const data = await response.json();

            // El mensaje se agregará automáticamente vía Pusher
            // pero puedes agregarlo manualmente si prefieres
            if (data.data) {
                setMessages((prev) => [...prev, data.data]);
            }

            return data;
        } catch (error) {
            console.error('Error al enviar mensaje:', error);
            throw error;
        }
    };

    // Función para marcar como leído
    const markAsRead = async () => {
        try {
            await fetch(`${API_BASE_URL}/orders/${orderId}/chat/mark-read`, {
                method: 'PUT',
                headers: {
                    Authorization: `Bearer ${token}`,
                    Accept: 'application/json',
                },
            });
        } catch (error) {
            console.error('Error al marcar como leído:', error);
        }
    };

    return {
        messages,
        loading,
        sendMessage,
        markAsRead,
    };
};

export default useOrderChat;
```

### Uso del Hook

```javascript
import useOrderChat from './hooks/useOrderChat';

const ChatScreen = ({ orderId, token }) => {
    const { messages, loading, sendMessage, markAsRead } = useOrderChat(orderId, token);
    const [messageText, setMessageText] = useState('');

    const handleSend = async () => {
        if (messageText.trim()) {
            await sendMessage(messageText);
            setMessageText('');
        }
    };

    if (loading) {
        return <ActivityIndicator />;
    }

    return (
        <View>
            <FlatList data={messages} keyExtractor={(item) => item.id.toString()} renderItem={({ item }) => <MessageBubble message={item} />} />
            <TextInput value={messageText} onChangeText={setMessageText} placeholder="Escribe un mensaje..." />
            <Button title="Enviar" onPress={handleSend} />
        </View>
    );
};
```

---

## Configuración de Variables de Entorno

En tu aplicación React Native, crea un archivo `.env` o configura estas variables:

```env
API_BASE_URL=https://tu-dominio.com/api
PUSHER_APP_KEY=tu_pusher_key
PUSHER_CLUSTER=tu_cluster
```

---

## Notas Importantes

1. **Autenticación**: Todos los endpoints requieren token Bearer válido
2. **Permisos**:
    - Cliente solo puede acceder a sus órdenes
    - Delivery solo puede acceder después de ser asignado
    - Admin puede acceder a todas las órdenes
3. **Delivery y Mensajes Previos**: El delivery NO ve mensajes anteriores a su asignación. Esto es intencional.
4. **Tiempo Real**: Los mensajes nuevos se reciben automáticamente vía Pusher sin necesidad de refrescar
5. **Archivos**: Tamaño máximo 10MB por archivo
6. **Tipos de Mensaje**: `text`, `image`, `file`

---

## Ejemplo de Manejo de Errores

```javascript
const handleSendMessage = async (text) => {
    try {
        await sendMessage(text);
    } catch (error) {
        if (error.response?.status === 403) {
            Alert.alert('Error', 'No tienes permisos para enviar mensajes en este chat');
        } else if (error.response?.status === 404) {
            Alert.alert('Error', 'La orden no existe');
        } else {
            Alert.alert('Error', 'No se pudo enviar el mensaje. Intenta de nuevo.');
        }
    }
};
```

---

## Códigos de Estado HTTP

| Código | Descripción                 |
| ------ | --------------------------- |
| 200    | Éxito                       |
| 201    | Mensaje creado exitosamente |
| 400    | Datos inválidos             |
| 401    | No autenticado              |
| 403    | Sin permisos                |
| 404    | Recurso no encontrado       |
| 500    | Error del servidor          |

---

## Soporte

Para más información sobre la API completa, consulta `API_DOCUMENTATION.md`.
