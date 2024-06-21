<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
        try {
            Log::info('Datos de la solicitud de login:', $request->except(['password']));

            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string',
            ]);

            $credentials = [
                'name' => htmlspecialchars($request->input('username'), ENT_QUOTES, 'UTF-8'),
                'password' => $request->input('password')
            ];

            if (!Auth::attempt($credentials)) {
                Log::warning('Detalles de inicio de sesión inválidos:', ['name' => $credentials['name']]);
                return response()->json(['message' => 'Detalles de inicio de sesión inválidos'], 401);
            }

            $user = Auth::user();
            Log::info('Usuario autenticado:', ['user_id' => $user->id, 'name' => $user->name]);
            //nombre del token
            $tokenName = 'TOKEN CLIENTE: ' . $user->name;
            // $abilities = ['*']; // Permitir todas las habilidades
            $token = $user->createToken($tokenName)->plainTextToken;

            // Obtener los clientes asociados al usuario
            $clientes = $user->clientes;
            return response()->json([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'clientes' => $clientes
            ]);
        } catch (\Exception $e) {
            Log::error('Error en la solicitud de login:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Autenticación fallida'], 401);
        }
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
}
