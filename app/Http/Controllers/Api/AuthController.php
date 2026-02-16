<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login normal con CI y Password
     */
    public function login(Request $request): JsonResponse
    {
        // Allow 'ci' or 'login_input' for backward compatibility during frontend cache refresh
        $loginInput = $request->login_input ?? $request->ci;

        if (!$loginInput || !$request->password) {
            return response()->json([
                'message' => 'Faltan credenciales (login_input/ci y password)',
                'errors' => ['login_input' => ['El campo es obligatorio']]
            ], 422);
        }



        // Determinar si es email o CI
        $field = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'ci';

        $user = User::where($field, $loginInput)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'login_input' => ['Las credenciales son incorrectas.'],
            ]);
        }

        if (!$user->activo) {
             throw ValidationException::withMessages([
                'login_input' => ['Usuario desactivado.'],
            ]);
        }

        if (!$token = auth('api')->login($user)) {
             return response()->json(['error' => 'No se pudo crear el token'], 500);
        }

        $user->load(['userSystems', 'rol.permissions', 'individualPermissions', 'sede']);

        return response()->json([
            'success' => true,
            'message' => 'Bienvenido a SISPO',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60
            ]
        ]);
    }

    /**
     * Redirigir a Google
     */
    public function redirectToGoogle(): JsonResponse
    {
        // Se usa stateless() para APIs
        $url = Socialite::driver('google')->stateless()->redirect()->getTargetUrl();
        return response()->json(['url' => $url]);
    }

    /**
     * Callback de Google
     */
    public function handleGoogleCallback(): JsonResponse
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            // Buscar si ya existe por google_id o email
            $user = User::where('google_id', $googleUser->id)
                        ->orWhere('email', $googleUser->email)
                        ->first();

            if (!$user) {
                // CREAR NUEVO USUARIO AUTOMÁTICAMENTE
                // Como CI es nullable ahora, podemos crearlo sin CI.
                // Asumimos un rol por defecto (ej. rol_id = 2 'Usuario' o el que tengas configurado)
                // OJO: Asegúrate de tener un Rol por defecto o ajustar esto.

                $user = User::create([
                    'nombres'   => $googleUser->user['given_name'] ?? $googleUser->name,
                    'apellidos' => $googleUser->user['family_name'] ?? '',
                    'email'     => $googleUser->email,
                    'google_id' => $googleUser->id,
                    'password'  => Hash::make(\Illuminate\Support\Str::random(16)), // Password aleatorio
                    'activo'    => true,
                    'rol_id'    => 2, // <--- ID del Rol por defecto para nuevos usuarios (Ajustar según tu DB)
                    // 'ci' se queda null
                ]);
            } else {
                // Si existe pero no tenía google_id, lo actualizamos
                if (!$user->google_id) {
                    $user->google_id = $googleUser->id;
                    $user->save();
                }
            }

            if (!$user->activo) {
                return response()->json(['message' => 'Usuario desactivado'], 401);
            }

            // Generar Token
            // Generar Token
            $token = $user->createToken('sispo-token')->plainTextToken;

            // REDIRECT TO FRONTEND
            // Codificamos el usuario para pasarlo limpio por URL
            $userData = base64_encode(json_encode($user));
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:9000/login');

            return redirect("$frontendUrl?token=$token&user=$userData");

        } catch (\Exception $e) {
            // En caso de error, también redirigimos al login con error
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:9000/login');
            return redirect("$frontendUrl?error=" . urlencode($e->getMessage()));
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true, 'message' => 'Logout exitoso']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['userSystems', 'rol.permissions', 'individualPermissions', 'sede']);
        return response()->json(['user' => $user]);
    }
}
