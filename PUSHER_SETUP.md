# Configuración de Pusher para Chat en Tiempo Real

Este documento explica cómo configurar Pusher para habilitar el chat en tiempo real en la aplicación.

## Paso 1: Crear cuenta en Pusher

1. Ve a [https://pusher.com/](https://pusher.com/)
2. Crea una cuenta gratuita o inicia sesión
3. Una vez dentro, crea una nueva aplicación (o selecciona una existente)

## Paso 2: Obtener credenciales

En el dashboard de Pusher, verás las siguientes credenciales que necesitarás:

- **App ID**: Identificador único de tu aplicación
- **Key**: Clave pública de tu aplicación
- **Secret**: Clave secreta (manténla privada)
- **Cluster**: Región/cluster donde está alojada tu app (ej: `mt1`, `eu`, `ap-southeast-1`)

## Paso 3: Configurar variables de entorno

Edita tu archivo `.env` y agrega las siguientes variables:

```env
# Broadcasting - Usar Pusher
BROADCAST_CONNECTION=pusher

# Pusher Configuration
PUSHER_APP_ID=tu_app_id_aqui
PUSHER_APP_KEY=tu_app_key_aqui
PUSHER_APP_SECRET=tu_app_secret_aqui
PUSHER_APP_CLUSTER=tu_cluster_aqui

# Variables para el frontend (Vite)
VITE_PUSHER_APP_KEY=tu_app_key_aqui
VITE_PUSHER_CLUSTER=tu_cluster_aqui
```

**Importante**: 
- `VITE_PUSHER_APP_KEY` y `VITE_PUSHER_CLUSTER` deben ser las mismas que `PUSHER_APP_KEY` y `PUSHER_APP_CLUSTER`
- Las variables `VITE_*` son accesibles desde el frontend
- Las variables sin `VITE_` son solo para el backend

## Paso 4: Limpiar cache

Después de configurar las variables, ejecuta:

```bash
php artisan config:clear
php artisan cache:clear
```

## Paso 5: Reiniciar el servidor de desarrollo

Si estás usando `npm run dev` para Vite, reinícialo para que cargue las nuevas variables de entorno:

```bash
# Detén el servidor (Ctrl+C) y vuelve a iniciarlo
npm run dev
```

## Verificación

1. Inicia sesión como admin
2. Ve a `/admin/orders/{id}` (por ejemplo: `/admin/orders/13`)
3. Abre la pestaña "Chat"
4. Deberías ver un punto verde indicando que Pusher está conectado
5. Si envías un mensaje, debería aparecer instantáneamente (tiempo real)

## Solución de problemas

### Error: "Pusher no configurado"
- Verifica que `VITE_PUSHER_APP_KEY` esté en tu `.env`
- Asegúrate de haber reiniciado `npm run dev` después de agregar las variables
- Verifica que las variables no tengan espacios o caracteres extraños

### Error de conexión
- Verifica que `PUSHER_APP_CLUSTER` coincida con el cluster de tu aplicación en Pusher
- Asegúrate de que `BROADCAST_CONNECTION=pusher` esté configurado
- Revisa la consola del navegador para más detalles del error

### Los mensajes no aparecen en tiempo real
- Verifica que el job queue esté corriendo: `php artisan queue:work`
- O usa `QUEUE_CONNECTION=sync` en `.env` para desarrollo (no requiere queue worker)
- Verifica que `/broadcasting/auth` esté accesible y retorne 200

## Plan Gratuito de Pusher

El plan gratuito de Pusher incluye:
- Hasta 200,000 mensajes/día
- 100 conexiones simultáneas
- Suficiente para desarrollo y proyectos pequeños

Para producción, considera el plan de pago según tus necesidades.

