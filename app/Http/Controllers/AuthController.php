<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Maneja la solicitud de inicio de sesión de un usuario en el frontend.
     *
     * @desc Este método valida las credenciales del usuario, intenta autenticarlas y, si tiene éxito, genera un token de acceso.
     * @param Request $request La solicitud HTTP que contiene las credenciales del usuario.
     * @return \Illuminate\Http\JsonResponse La respuesta JSON con el token de acceso o un mensaje de error.
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255',
            'password' => 'required|string|min:8',
        ]);

        $username = filter_var($request->input('username'), FILTER_SANITIZE_SPECIAL_CHARS);
        $password = $request->input('password');
        $credentials = [
            'name' => $username,
            'password' => $password
        ];

        $this->checkTooManyLoginAttempts($request);

        if (!Auth::attempt($credentials)) {
            $this->incrementLoginAttempts($request);
            Log::warning('Detalles de inicio de sesión inválidos:', ['name' => $credentials['name']]);
            throw ValidationException::withMessages([
                'username' => [trans('auth.failed')],
            ]);
        }

        $user = Auth::user();

        if ($user->activo === 'n') {
            Auth::logout();
            return response()->json(['message' => 'Este cliente está inactivo'], 403);
        }

        $tokenName = 'TOKEN CLIENTE: ' . $user->name;
        $token = $user->createToken($tokenName)->plainTextToken;
        $clientesEndpointIds = $user->clientes->pluck('cliente_endpoint_id');

        $this->clearLoginAttempts($request);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'clientes_endpoint_ids' => $clientesEndpointIds
        ]);
    }

    /**
     * Maneja la solicitud de cierre de sesión de un usuario.
     *
     * @desc Este método revoca el token de acceso actual del usuario, cerrando su sesión.
     * @param Request $request La solicitud HTTP que contiene la información del usuario.
     * @return \Illuminate\Http\JsonResponse La respuesta JSON con un mensaje de éxito.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada con éxito']);
    }

    protected function checkTooManyLoginAttempts(Request $request)
    {
        $key = $this->throttleKey($request);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            Log::warning('Demasiados intentos de inicio de sesión.', ['name' => $request->input('username')]);
            throw ValidationException::withMessages([
                'username' => [trans('auth.throttle', [
                    'seconds' => RateLimiter::availableIn($key)
                ])],
            ]);
        }
    }

    protected function incrementLoginAttempts(Request $request)
    {
        RateLimiter::hit($this->throttleKey($request), 60);
    }

    protected function clearLoginAttempts(Request $request)
    {
        RateLimiter::clear($this->throttleKey($request));
    }

    protected function throttleKey(Request $request)
    {
        return strtolower($request->input('username')).'|'.$request->ip();
    }
}
