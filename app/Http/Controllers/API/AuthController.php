<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\ConfirmRegister;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    /**
     * Maneja el registro de usuarios.
     *
     * Este método valida los datos de la solicitud entrante, crea un nuevo usuario con la información
     * proporcionada, asigna el rol de "cliente" al usuario y genera un token de API para el usuario.
     * Si ocurre algún error durante el proceso, revierte la transacción y devuelve una respuesta de error.
     *
     * @param \Illuminate\Http\Request $req La solicitud HTTP entrante que contiene los datos de registro del usuario.
     *
     * @return \Illuminate\Http\JsonResponse Una respuesta JSON que contiene:
     * - Un mensaje de éxito, el token de API generado y el objeto del usuario creado si el registro es exitoso.
     * - Un mensaje de error con un código de estado 500 si ocurre un error durante el proceso.
     *
     * @throws \Illuminate\Validation\ValidationException Si la validación de los datos de la solicitud falla.
     */
    public function register(Request $req)
    {

        ## Validacion de datos
        $rules = [
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'sometimes|nullable|min:6',
            'cedula_type' => 'nullable|sometimes|min:1|max:2|in:v,j,e,g,r,p',
            'cedula_ID' => 'nullable|sometimes|min:7|max:20',
            'tel' => 'nullable|sometimes|unique:users,tel|regex:/^[0-9]{10,15}$/', ## Solo permite números de 10 a 15 dígitos, útil para prevenir entradas como letras o símbolos.
            'google_id' => 'sometimes|nullable|string|max:255', ## Google ID opcional
            'avatar' => 'sometimes|nullable|url|max:255', ## URL del avatar opcional
        ];

        if (!$req->filled('google_id')) {
            $rules['email'] .= '|unique:users';
        }

        $req->validate($rules);



        DB::beginTransaction();
        try {
            ## Crear el usuario cliente
            $user = User::firstOrCreate(
                ['email' => $req->email],
                [
                    'name' => $req->name,
                    'email' => $req->email,
                    'password' => $req->password ? Hash::make($req->password) : null, ## Encrtiptar contraseña
                    'cedula_type' => $req->cedula_type ?? null, ## Si no se proporciona, se asigna null
                    'cedula_ID' => $req->cedula_ID ?? null, ## Si no se proporciona, se asigna null
                    'tel' => $req->tel ?? null, ## Si no se proporciona, se asigna null
                    'google_id' => $req->google_id ?? null, ## Si no se proporciona, se asigna null
                    'avatar' => $req->avatar ?? null, ## Si no se proporciona, se asigna null
                ]
            );
            ## Asignar rol cliente usando Spatie
            $user->assignRole('client');
            
            ## Asignar órdenes pre-registradas si el usuario tiene teléfono o cédula
            $assignedOrders = [];
            if ($user->tel || $user->cedula_ID) {
                $assignedOrders = $user->assignPreRegisteredOrders();
            }
            
            DB::commit();

            ## Enviar correo de confirmación
            Mail::to($user->email)->send(new ConfirmRegister($user));

            ## Si se crea el usuario correctamente, se devuelve un token de acceso y el usuario
            return response()->json([
                'message' => 'Usuario creado correctamente',
                'token' => $user->createToken("api-token")->plainTextToken,
                'user' => [
                    ...$user->toArray(),
                    'roles' => $user->getRoleNames()->toArray(),
                    'is_admin' => $user->hasAnyRole(['admin', 'super_admin']),
                    'is_delivery' => $user->hasRole('delivery'),
                    'is_client' => $user->hasRole('client') || $user->getRoleNames()->isEmpty(),
                    'points' => $user->getAvailablePoints(),
                    'points_formatted' => number_format($user->getAvailablePoints(), 2),
                    'can_use_points' => $user->getAvailablePoints() >= 100,
                    'points_discount_available' => $user->calculatePointsDiscount((int) $user->getAvailablePoints()),
                ]
            ]);
        } catch (\Throwable $th) {
            ## Si ocurre un error, se hace rollback
            DB::rollBack();
            ## Si ocurre un error, se devuelve un error 500
            return response()->json([
                'message' => 'Error creando el usuario',
                'error' => $th->getMessage()
            ], 500);
        }
    }


    /**
     * Inicia sesión de un usuario y genera un token de acceso.
     *
     * @param \Illuminate\Http\Request $request La solicitud HTTP que contiene los datos de inicio de sesión.
     * 
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el token de acceso y los datos del usuario,
     *                                       o un mensaje de error si las credenciales son inválidas.
     * 
     * Validaciones:
     * - 'email': Requerido, debe ser un correo electrónico válido.
     * - 'password': Requerido.
     * - 'type': Opcional, debe ser uno de los valores: 'phone' o 'email'.
     * 
     * Lógica:
     * - Busca al usuario por su correo electrónico.
     * - Verifica que la contraseña proporcionada coincida con la almacenada.
     * - Si las credenciales son válidas, genera un token de acceso y lo devuelve junto con los datos del usuario.
     * - Si las credenciales son inválidas, devuelve un mensaje de error con código de estado 401.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email:exists:users,email',
            'password' => 'required',
            'type' => 'in:phone,email:default,email'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
                'errors' => ['Credenciales Inválidas, verifique su correo electrónico y contraseña']
            ], 401);
        }

        return response()->json([
            'message' => 'Usuario logeado correctamente',
            'token' => $user->createToken("api-token")->plainTextToken,
            'user' => [
                ...$user->toArray(),
                'roles' => $user->getRoleNames()->toArray(),
                'is_admin' => $user->hasAnyRole(['admin', 'super_admin']),
                'is_delivery' => $user->hasRole('delivery'),
                'is_client' => $user->hasRole('client') || $user->getRoleNames()->isEmpty(),
                'points' => $user->getAvailablePoints(),
                'points_formatted' => number_format($user->getAvailablePoints(), 2),
                'can_use_points' => $user->getAvailablePoints() >= 100,
                'points_discount_available' => $user->calculatePointsDiscount((int) $user->getAvailablePoints()),
            ]
        ]);
    }
}
