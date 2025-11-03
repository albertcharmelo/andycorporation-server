<?php

/**
 * Script para agregar las variables VITE_PUSHER_* al .env
 * Ejecutar: php scripts/add-pusher-vite-vars.php
 */

$envFile = __DIR__ . '/../.env';

if (!file_exists($envFile)) {
    echo "❌ ERROR: No se encontró el archivo .env\n";
    exit(1);
}

// Leer el contenido actual
$content = file_get_contents($envFile);

// Verificar si ya existen las variables VITE_
if (strpos($content, 'VITE_PUSHER_APP_KEY') !== false && 
    strpos($content, 'VITE_PUSHER_CLUSTER') !== false) {
    echo "✅ Las variables VITE_PUSHER_APP_KEY y VITE_PUSHER_CLUSTER ya existen\n";
    exit(0);
}

// Extraer valores de PUSHER_APP_KEY y PUSHER_APP_CLUSTER
preg_match('/^PUSHER_APP_KEY=(.+)$/m', $content, $keyMatch);
preg_match('/^PUSHER_APP_CLUSTER=(.+)$/m', $content, $clusterMatch);

$appKey = isset($keyMatch[1]) ? trim($keyMatch[1]) : null;
$cluster = isset($clusterMatch[1]) ? trim($clusterMatch[1]) : null;

if (!$appKey || !$cluster) {
    echo "❌ ERROR: No se encontraron PUSHER_APP_KEY o PUSHER_APP_CLUSTER en .env\n";
    echo "   Asegúrate de configurar estas variables primero\n";
    exit(1);
}

// Cambiar BROADCAST_CONNECTION a pusher si está en 'log' o 'null'
$content = preg_replace(
    '/^BROADCAST_CONNECTION=(.*)$/m',
    'BROADCAST_CONNECTION=pusher',
    $content
);

// Agregar variables VITE_ si no existen
if (strpos($content, 'VITE_PUSHER_APP_KEY') === false) {
    $content .= "\n# Variables para el frontend (Vite)\n";
    $content .= "VITE_PUSHER_APP_KEY={$appKey}\n";
    $content .= "VITE_PUSHER_CLUSTER={$cluster}\n";
}

// Guardar el archivo
file_put_contents($envFile, $content);

echo "✅ Variables agregadas correctamente:\n";
echo "   VITE_PUSHER_APP_KEY={$appKey}\n";
echo "   VITE_PUSHER_CLUSTER={$cluster}\n";
echo "   BROADCAST_CONNECTION actualizado a 'pusher'\n\n";
echo "📝 Próximos pasos:\n";
echo "   1. Ejecuta: php artisan config:clear\n";
echo "   2. Reinicia el servidor de desarrollo: npm run dev\n";
echo "   3. Verifica el chat en /admin/orders/{id}\n";

