<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\PointTransaction;
use App\Models\User;
use Illuminate\Http\Request;

class PointController extends Controller
{
    /**
     * Listar puntos de todos los usuarios (admin).
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 20);
        $search = $request->query('search');

        $query = User::select('id', 'name', 'email', 'points', 'created_at')
            ->where('points', '>', 0);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('points', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Ver detalles de puntos de un usuario específico.
     */
    public function show($userId)
    {
        $user = User::with('pointTransactions.order:id,status,total')
            ->findOrFail($userId);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'points' => $user->getAvailablePoints(),
                ],
                'transactions' => $user->pointTransactions()->orderBy('created_at', 'desc')->limit(50)->get(),
                'statistics' => [
                    'total_earned' => $user->pointTransactions()->where('type', 'earned')->sum('points'),
                    'total_used' => $user->pointTransactions()->where('type', 'used')->sum('points'),
                    'current_balance' => $user->getAvailablePoints(),
                ],
            ],
        ]);
    }
}
