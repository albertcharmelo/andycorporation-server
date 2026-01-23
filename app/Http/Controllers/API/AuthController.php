<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\ConfirmRegister;
use App\Mail\PasswordResetOtp as PasswordResetOtpMail;
use App\Models\PasswordResetOtp;
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
            ## Buscar usuario existente
            $user = User::where('email', $req->email)->first();
            
            if ($user) {
                ## Usuario existe: actualizar google_id si viene con Google
                if ($req->filled('google_id')) {
                    $user->google_id = $req->google_id;
                    if ($req->filled('avatar')) {
                        $user->avatar = $req->avatar;
                    }
                    if ($req->filled('name')) {
                        $user->name = $req->name;
                    }
                    $user->save();
                }
            } else {
                ## Crear nuevo usuario
                $user = User::create([
                    'name' => $req->name,
                    'email' => $req->email,
                    'password' => $req->password ? Hash::make($req->password) : null, ## Encriptar contraseña
                    'cedula_type' => $req->cedula_type ?? null, ## Si no se proporciona, se asigna null
                    'cedula_ID' => $req->cedula_ID ?? null, ## Si no se proporciona, se asigna null
                    'tel' => $req->tel ?? null, ## Si no se proporciona, se asigna null
                    'google_id' => $req->google_id ?? null, ## Si no se proporciona, se asigna null
                    'avatar' => $req->avatar ?? null, ## Si no se proporciona, se asigna null
                ]);
            }
            
            ## Asignar rol cliente usando Spatie (si no lo tiene)
            if (!$user->hasRole('client')) {
                $user->assignRole('client');
            }
            
            ## Asignar órdenes pre-registradas si el usuario tiene teléfono o cédula
            $assignedOrders = [];
            if ($user->tel || $user->cedula_ID) {
                $assignedOrders = $user->assignPreRegisteredOrders();
            }
            
            DB::commit();

            ## Enviar correo de confirmación (no debe afectar el registro si falla)
            try {
                Mail::to($user->email)->send(new ConfirmRegister($user));
            } catch (\Throwable $mailError) {
                // Log del error pero no fallar el registro
                \Log::error('Error enviando correo de confirmación: ' . $mailError->getMessage(), [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $mailError->getMessage()
                ]);
            }

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

        if (!$user) {
            return response()->json([
                'message' => 'Invalid credentials',
                'errors' => ['Credenciales Inválidas, verifique su correo electrónico y contraseña']
            ], 401);
        }

        // Verificar si el usuario tiene contraseña
        if (!$user->password) {
            return response()->json([
                'message' => 'Invalid credentials',
                'errors' => ['Este usuario fue registrado con Google. Por favor, inicia sesión con Google.']
            ], 401);
        }

        // Verificar contraseña
        if (!Hash::check($request->password, $user->password)) {
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

    /**
     * Solicita un código OTP para recuperación de contraseña.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $email = $request->email;
        $user = User::where('email', $email)->first();

        // Invalidar OTPs previos del mismo email
        PasswordResetOtp::forEmail($email)->update(['used_at' => now()]);

        // Generar OTP de 6 dígitos
        $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Crear nuevo OTP con expiración de 15 minutos
        $otp = PasswordResetOtp::create([
            'email' => $email,
            'otp_code' => $otpCode,
            'expires_at' => now()->addMinutes(15),
        ]);

        // Enviar email con OTP
        Mail::to($email)->send(new PasswordResetOtpMail($otpCode, $user->name));

        return response()->json([
            'message' => 'Código OTP enviado a tu correo',
        ]);
    }

    /**
     * Verifica un código OTP.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp_code' => 'required|string|size:6',
        ]);

        $otp = PasswordResetOtp::forEmail($request->email)
            ->where('otp_code', $request->otp_code)
            ->active()
            ->first();

        if (!$otp) {
            return response()->json([
                'message' => 'Código OTP inválido o expirado',
                'verified' => false,
            ], 400);
        }

        return response()->json([
            'message' => 'OTP válido',
            'verified' => true,
        ]);
    }

    /**
     * Restablece la contraseña usando un código OTP válido.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp_code' => 'required|string|size:6',
            'password' => 'required|min:6|confirmed',
        ]);

        // Verificar OTP válido
        $otp = PasswordResetOtp::forEmail($request->email)
            ->where('otp_code', $request->otp_code)
            ->active()
            ->first();

        if (!$otp) {
            return response()->json([
                'message' => 'Código OTP inválido o expirado',
            ], 400);
        }

        // Actualizar contraseña del usuario
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        // Marcar OTP como usado
        $otp->markAsUsed();

        // Invalidar todos los tokens de sesión del usuario (opcional, seguridad)
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Contraseña restablecida exitosamente',
        ]);
    }

    /**
     * Cierra sesión del usuario actual invalidando su token.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // Eliminar el token actual del usuario
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada exitosamente',
        ]);
    }

    /**
     * Actualiza el perfil del usuario autenticado.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado',
            ], 401);
        }

        // Validación de datos
        $rules = [
            'name' => 'sometimes|string|max:255',
            'cedula_type' => 'nullable|sometimes|in:v,j,e,g,r,p',
            'cedula_ID' => 'nullable|sometimes|min:7|max:20',
            'tel' => 'nullable|sometimes|regex:/^[0-9]{10,15}$/|unique:users,tel,' . $user->id,
        ];

        $request->validate($rules);

        // Actualizar solo los campos proporcionados
        if ($request->filled('name')) {
            $user->name = $request->name;
        }
        if ($request->filled('cedula_type')) {
            $user->cedula_type = $request->cedula_type;
        }
        if ($request->filled('cedula_ID')) {
            $user->cedula_ID = strtolower($request->cedula_ID);
        }
        if ($request->filled('tel')) {
            $user->tel = $request->tel;
        }

        $user->save();

        // Asignar órdenes pre-registradas si el usuario ahora tiene teléfono o cédula
        if ($user->tel || $user->cedula_ID) {
            $user->assignPreRegisteredOrders();
        }

        // Refrescar el usuario para obtener los datos actualizados
        $user->refresh();

        return response()->json([
            'message' => 'Perfil actualizado exitosamente',
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
            ],
        ]);
    }
}
