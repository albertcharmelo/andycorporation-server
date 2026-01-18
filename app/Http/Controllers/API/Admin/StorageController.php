<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentProof;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class StorageController extends Controller
{
    /**
     * Verificar configuración de almacenamiento.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function verify()
    {
        try {
            $storagePath = storage_path('app/public');
            $publicPath = public_path('storage');
            $paymentProofsPath = storage_path('app/public/payment_proofs');

            // Verificar si el symlink existe
            $symlinkExists = is_link($publicPath) || (is_dir($publicPath) && file_exists($publicPath));

            // Verificar si el directorio de payment_proofs existe
            $paymentProofsDirExists = is_dir($paymentProofsPath);

            // Contar archivos de comprobantes
            $paymentProofsCount = 0;
            $totalSize = 0;

            if ($paymentProofsDirExists) {
                $files = File::files($paymentProofsPath);
                $paymentProofsCount = count($files);
                foreach ($files as $file) {
                    $totalSize += $file->getSize();
                }
            }

            // Formatear tamaño total
            $totalSizeHuman = $this->formatBytes($totalSize);

            return response()->json([
                'success' => true,
                'data' => [
                    'symlink_exists' => $symlinkExists,
                    'storage_path' => $storagePath,
                    'public_path' => $publicPath,
                    'payment_proofs_path' => $paymentProofsPath,
                    'payment_proofs_dir_exists' => $paymentProofsDirExists,
                    'payment_proofs_count' => $paymentProofsCount,
                    'total_size' => $totalSize,
                    'total_size_human' => $totalSizeHuman,
                    'app_url' => config('app.url'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar el almacenamiento',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar todos los comprobantes de pago.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function listPaymentProofs()
    {
        try {
            $paymentProofs = PaymentProof::with('order:id,order_number,created_at')
                ->orderBy('created_at', 'desc')
                ->get();

            $files = [];
            $totalSize = 0;

            foreach ($paymentProofs as $proof) {
                $filePath = $proof->file_path;
                $fullPath = storage_path('app/public/' . $filePath);
                $exists = file_exists($fullPath);
                $fileSize = $exists ? filesize($fullPath) : 0;
                $totalSize += $fileSize;

                // Generar URL
                $url = Storage::url($filePath);

                // Obtener nombre del archivo
                $fileName = basename($filePath);

                $files[] = [
                    'order_id' => $proof->order_id,
                    'order_number' => $proof->order?->order_number,
                    'file_path' => $filePath,
                    'file_name' => $fileName,
                    'file_size' => $fileSize,
                    'file_size_human' => $this->formatBytes($fileSize),
                    'url' => $url,
                    'exists' => $exists,
                    'created_at' => $proof->created_at->toISOString(),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'files' => $files,
                    'total' => count($files),
                    'total_size' => $totalSize,
                    'total_size_human' => $this->formatBytes($totalSize),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al listar los comprobantes de pago',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verificar un comprobante de pago específico por order ID.
     *
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyPaymentProof($orderId)
    {
        try {
            $order = Order::with('paymentProof')->findOrFail($orderId);

            if (!$order->paymentProof) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta orden no tiene comprobante de pago',
                ], 404);
            }

            $proof = $order->paymentProof;
            $filePath = $proof->file_path;
            $fullPath = storage_path('app/public/' . $filePath);
            $exists = file_exists($fullPath);
            $fileSize = $exists ? filesize($fullPath) : 0;

            // Generar URL
            $url = Storage::url($filePath);

            // Verificar si el archivo es accesible públicamente
            $publiclyAccessible = false;
            if ($exists) {
                $publicPath = public_path('storage/' . $filePath);
                $publiclyAccessible = file_exists($publicPath) || is_link(public_path('storage'));
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'file_path' => $filePath,
                    'file_name' => basename($filePath),
                    'file_size' => $fileSize,
                    'file_size_human' => $this->formatBytes($fileSize),
                    'url' => $url,
                    'exists' => $exists,
                    'publicly_accessible' => $publiclyAccessible,
                    'full_path' => $fullPath,
                    'public_path' => public_path('storage/' . $filePath),
                    'created_at' => $proof->created_at->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar el comprobante de pago',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Formatear bytes a formato legible.
     *
     * @param int $bytes
     * @return string
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
