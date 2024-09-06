<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\TokensPassword;
use Illuminate\Support\Str;
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
            return response()->json(['tittle' => 'Usuario invalido', 'message' => 'Usuario o contraseña mal ingresados.'], 422);
            throw ValidationException::withMessages([
                'username' => [trans('auth.failed')],
            ]);
        }

        $user = Auth::user();

        if ($user->activo === 'n') {
            Auth::logout();
            return response()->json(['tittle' => 'Usuario inactivo', 'message' => 'Este cliente está inactivo'], 403);
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

    public function generateToken(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->input('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json(['error' => 'Correo no encontrado en la base de datos.'], 404);
        }

        $token = Str::random(30);
        $username = $user->name;
        $id_username = $user->id;
        $expiresAt = Carbon::now()->addHours(1);

        TokensPassword::create([
            'id_username' => $id_username,
            'username' => $username,
            'email' => $email,
            'token' => $token,
            'expires_at' => $expiresAt,
        ]);

        return response()->json(['token' => $token], 200);
    }

    public function setVerificarToken($token)
    {
        try{
            $tokenResultado = TokensPassword::where('token', $token)->first();

            if (!$tokenResultado) {
                return response()->json(['success' => false, 'message' => 'Token no encontrado.', 'codigo' => 404], 404);
            }

            if (Carbon::now()->greaterThan($tokenResultado->expires_at)) {
                return response()->json(['success' => false, 'message' => 'Token expirado.', 'codigo' => 403], 403);
            }

            return response()->json(['success' => true, 'message' => 'Token verificado.', 'id_username' => $tokenResultado->id_username], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Server error.', 'error' => $e->getMessage(), 'codigo' => 500], 500);
        }
    }

    public function deleteToken($token)
    {
        try {
            TokensPassword::where('token', $token)->delete();

            return response()->json(['success' => true, 'message' => 'Token expirado.']);
        } catch (\Throwable $th) {
            Log::error("Error a la hora de borrar el token.");
            return response()->json(['success' => false, 'message' => 'Error a la hora de borrar el token.']);
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
