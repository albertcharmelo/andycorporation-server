<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    /**
     * Guardar dirección: Recibir los datos de la dirección de entrega del usuario y almacenarlos.
     */
    public function store(Request $request)
    {
        $request->validate([
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'name'           => 'nullable|string|max:100',
            'referencia'     => 'nullable|string|max:255',
            'postal_code'    => 'nullable|string|max:20',
            'is_default'     => 'boolean',
            'latitude'       => 'nullable|numeric|between:-90,90',   // Validación para latitud
            'longitude'      => 'nullable|numeric|between:-180,180', // Validación para longitud
        ]);

        $user = $request->user();

        try {
            DB::beginTransaction();

            if ($request->boolean('is_default')) {
                $user->addresses()->update(['is_default' => false]);
            }

            $address = $user->addresses()->create($request->all());

            DB::commit();
            return response()->json(['message' => 'Dirección guardada exitosamente.', 'address' => $address], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al guardar la dirección: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Mostrar todas las direcciones guardadas previamente por el usuario.
     */
    public function index(Request $request)
    {
        $user      = $request->user();
        $addresses = $user->addresses()->orderBy('is_default', 'desc')->get();

        return response()->json(['addresses' => $addresses]);
    }

    /**
     * Seleccionar/Mostrar una dirección específica por ID.
     */
    public function show(Request $request, $addressId)
    {
        $user = $request->user();

        $address = $user->addresses()->find($addressId);

        if (! $address) {
            return response()->json(['message' => 'Dirección no encontrada.'], 404);
        }

        return response()->json(['address' => $address]);
    }

    /**
     * Actualizar los datos de una dirección existente.
     */
    public function update(Request $request, $addressId)
    {
        $request->validate([
            'address_line_1' => 'sometimes|required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'name'           => 'nullable|string|max:100',
            'referencia'     => 'nullable|string|max:255',
            'postal_code'    => 'nullable|string|max:20',
            'is_default'     => 'boolean',
            'latitude'       => 'nullable|numeric|between:-90,90',   // Validación para latitud
            'longitude'      => 'nullable|numeric|between:-180,180', // Validación para longitud
        ]);

        $user = $request->user();

        $address = $user->addresses()->find($addressId);

        if (! $address) {
            return response()->json(['message' => 'Dirección no encontrada.'], 404);
        }

        try {
            DB::beginTransaction();

            if ($request->has('is_default') && $request->boolean('is_default')) {
                $user->addresses()->where('id', '!=', $addressId)->update(['is_default' => false]);
            }

            $address->update($request->all());

            DB::commit();
            return response()->json(['message' => 'Dirección actualizada exitosamente.', 'address' => $address]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar la dirección: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar una dirección guardada.
     */
    public function destroy(Request $request, $addressId)
    {
        $user = $request->user();

        $deleted = $user->addresses()->where('id', $addressId)->delete();

        if ($deleted) {
            return response()->json(['message' => 'Dirección eliminada exitosamente.']);
        } else {
            return response()->json(['message' => 'Dirección no encontrada o no pertenece al usuario.'], 404);
        }
    }

    /**
     * Establecer una dirección como predeterminada.
     */
    public function setDefault(Request $request, $addressId)
    {
        $user = $request->user();

        try {
            DB::beginTransaction();

            $user->addresses()->update(['is_default' => false]);

            $address = $user->addresses()->find($addressId);

            if (! $address) {
                DB::rollBack();
                return response()->json(['message' => 'Dirección no encontrada.'], 404);
            }

            $address->is_default = true;
            $address->save();

            DB::commit();
            return response()->json(['message' => 'Dirección establecida como predeterminada exitosamente.', 'address' => $address]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al establecer la dirección predeterminada: ' . $e->getMessage()], 500);
        }
    }

}
