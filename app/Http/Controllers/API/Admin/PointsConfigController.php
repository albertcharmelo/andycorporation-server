<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\PointsConfig;
use Illuminate\Http\Request;

class PointsConfigController extends Controller
{
    /**
     * Obtener la configuración actual de puntos.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $config = PointsConfig::getActive() ?? PointsConfig::first();

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'No hay configuración de puntos disponible',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }

    /**
     * Actualizar la configuración de puntos.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        $request->validate([
            'points_per_currency' => 'required|numeric|min:0.01',
            'currency' => 'required|in:BS,USD',
            'is_active' => 'boolean',
        ]);

        $config = PointsConfig::first();

        if (!$config) {
            $config = PointsConfig::create($request->only([
                'points_per_currency',
                'currency',
                'is_active',
            ]));
        } else {
            $config->update($request->only([
                'points_per_currency',
                'currency',
                'is_active',
            ]));
        }

        return response()->json([
            'success' => true,
            'message' => 'Configuración de puntos actualizada exitosamente',
            'data' => $config,
        ]);
    }

    /**
     * Calcular puntos para un monto dado (endpoint de prueba).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function calculatePoints(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|in:BS,USD',
        ]);

        $config = PointsConfig::getActive();

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'No hay configuración de puntos activa',
            ], 404);
        }

        $currency = $request->currency ?? $config->currency;
        $points = $config->calculatePoints($request->amount, $currency);

        return response()->json([
            'success' => true,
            'data' => [
                'amount' => $request->amount,
                'currency' => $currency,
                'points' => $points,
                'rate' => $config->points_per_currency,
            ],
        ]);
    }
}
