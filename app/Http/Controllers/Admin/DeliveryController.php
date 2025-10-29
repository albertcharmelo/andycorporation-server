<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeliveryController extends Controller
{
    /**
     * Mostrar lista de deliveries (Inertia).
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $deliveries = User::role('delivery')
            ->select('id', 'name', 'email', 'tel', 'created_at')
            ->withCount(['assignedOrders as active_orders' => function ($query) {
                $query->whereIn('status', ['paid', 'shipped']);
            }])
            ->withCount('assignedOrders as total_deliveries')
            ->get();

        return Inertia::render('admin/Deliveries', [
            'deliveries' => $deliveries,
        ]);
    }
}
