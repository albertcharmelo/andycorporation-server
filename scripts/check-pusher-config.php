<?php

/**
 * Script para verificar la configuración de Pusher
 * Ejecutar: php scripts/check-pusher-config.php
 */

echo "=== Verificación de Configuración de Pusher ===\n\n";

// Cargar .env manualmente
$envFile = __DIR__ . '/../.env';
if (!file_exists($envFile)) {
    echo "❌ ERROR: No se encontró el archivo .env\n";
    echo "   Crea el archivo .env basándote en .env.example\n";
    exit(1);
}

// Leer variables del .env
$envVars = [];
$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) {
        continue;
    }
    list($key, $value) = explode('=', $line, 2);
    $envVars[trim($key)] = trim($value);
}

// Variables requeridas
$requiredVars = [
    'BROADCAST_CONNECTION' => 'pusher',
    'PUSHER_APP_ID' => null,
    'PUSHER_APP_KEY' => null,
    'PUSHER_APP_SECRET' => null,
    'PUSHER_APP_CLUSTER' => null,
    'VITE_PUSHER_APP_KEY' => null,
    'VITE_PUSHER_CLUSTER' => null,
];

$missing = [];
$configured = [];

foreach ($requiredVars as $var => $expectedValue) {
    $value = $envVars[$var] ?? null;
    
    if (empty($value) || $value === 'your_app_id' || $value === 'your_app_key' || 
        $value === 'your_app_secret' || $value === 'your_cluster') {
        $missing[] = $var;
        echo "❌ {$var}: NO CONFIGURADO\n";
    } else {
        $configured[] = $var;
        $displayValue = $var === 'PUSHER_APP_SECRET' ? str_repeat('*', strlen($value)) : $value;
        echo "✅ {$var}: {$displayValue}\n";
        
        // Verificar que VITE_ variables coincidan con las de backend
        if ($var === 'PUSHER_APP_KEY' && isset($envVars['VITE_PUSHER_APP_KEY'])) {
            if ($value !== $envVars['VITE_PUSHER_APP_KEY']) {
                echo "   ⚠️  ADVERTENCIA: VITE_PUSHER_APP_KEY no coincide con PUSHER_APP_KEY\n";
            }
        }
        if ($var === 'PUSHER_APP_CLUSTER' && isset($envVars['VITE_PUSHER_CLUSTER'])) {
            if ($value !== $envVars['VITE_PUSHER_CLUSTER']) {
                echo "   ⚠️  ADVERTENCIA: VITE_PUSHER_CLUSTER no coincide con PUSHER_APP_CLUSTER\n";
            }
        }
    }
}

echo "\n";

if (empty($missing)) {
    echo "✅ Todas las variables están configuradas correctamente!\n\n";
    echo "📝 Próximos pasos:\n";
    echo "   1. Ejecuta: php artisan config:clear\n";
    echo "   2. Reinicia el servidor de desarrollo (npm run dev)\n";
    echo "   3. Verifica el chat en /admin/orders/{id}\n";
} else {
    echo "❌ Faltan las siguientes variables:\n";
    foreach ($missing as $var) {
        echo "   - {$var}\n";
    }
    echo "\n";
    echo "📝 Agrega estas variables a tu archivo .env:\n";
    echo "   BROADCAST_CONNECTION=pusher\n";
    echo "   PUSHER_APP_ID=tu_app_id_aqui\n";
    echo "   PUSHER_APP_KEY=tu_app_key_aqui\n";
    echo "   PUSHER_APP_SECRET=tu_app_secret_aqui\n";
    echo "   PUSHER_APP_CLUSTER=tu_cluster_aqui\n";
    echo "   VITE_PUSHER_APP_KEY=tu_app_key_aqui\n";
    echo "   VITE_PUSHER_CLUSTER=tu_cluster_aqui\n";
    echo "\n";
    echo "💡 Consulta PUSHER_SETUP.md para más detalles\n";
    exit(1);
}

exit(0);

