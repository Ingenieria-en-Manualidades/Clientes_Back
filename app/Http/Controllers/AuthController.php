<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\TokensPassword;
use App\Mail\RecoverPasswordEmail;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use Exception;


class AuthController extends Controller
{
    /**
     * Maneja la solicitud de inicio de sesión de un usuario.
     *
     * @desc Este método toma los datos cifrados de las credenciales, los desencripta, y valida al usuario.
     *       Si la autenticación es exitosa, se genera un token de acceso para el usuario.
     * @param Request $request La solicitud HTTP que contiene los datos cifrados del usuario.
     * @return \Illuminate\Http\JsonResponse Respuesta con el token de acceso o un mensaje de error.
     */
    public function login(Request $request)
    {
        function decryptAES($encryptedHex, $keyValue)
{
    $salt = 'salt'; 
    $iterations = 100; 

    $ivHex = substr($encryptedHex, 0, 32);
    $cipherTextHex = substr($encryptedHex, 32); 

    $iv = hex2bin($ivHex);
    $cipherText = hex2bin($cipherTextHex);

    $key = hash_pbkdf2('sha256', $keyValue, $salt, $iterations, 32, true);

    $decrypted = openssl_decrypt($cipherText, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

    return $decrypted;
}


try {
    $encryptedHex = $request->input('encrypted_data'); 
    if (!$encryptedHex) {
        throw new Exception('Datos cifrados no proporcionados.');
    }
    $keyValue = env('KEY_ENCRYPTED'); 

    $decryptedData = decryptAES($encryptedHex, $keyValue);
    $credentials = json_decode($decryptedData, true); 
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Error al decodificar los datos desencriptados.');
    }

    $decryptedUsername = $credentials['username'] ?? null;
    $decryptedPassword = $credentials['password'] ?? null;

    $username = filter_var($decryptedUsername, FILTER_SANITIZE_SPECIAL_CHARS);
    $password = $decryptedPassword;

    $credentials = [
        'name' => $username,
        'password' => $password
    ];

    $this->checkTooManyLoginAttempts($request);

    if (!Auth::attempt($credentials)) {
        $this->incrementLoginAttempts($request);
        Log::warning('Detalles de inicio de sesión inválidos:', ['name' => $credentials['name']]);
        return response()->json(['title' => 'Usuario inválido', 'message' => 'Usuario o contraseña incorrectos.'], 422);
    }

    $user = Auth::user();

    $permissions = DB::table('user_permission')
    ->join('users', 'users.id', '=', 'user_permission.user_id')
    ->join('permissions', 'permissions.id', '=', 'user_permission.permission_id')
    ->select('permissions.name')
    ->where('users.id', $user->id)
    ->whereNull('permissions.deleted_at')
    ->whereNull('user_permission.deleted_at')
    ->get();

    // Log::info("PERMISOS: ", ['permisos' => $permissions]);

    if ($user->activo === 'n') {
        Auth::logout();
        return response()->json(['title' => 'Usuario inactivo', 'message' => 'Este cliente está inactivo'], 403);
    }

    $tokenName = 'TOKEN CLIENTE: ' . $user->name;
    $token = $user->createToken($tokenName,['read', 'create', 'update', 'delete'])->plainTextToken;
    // $clientesEndpointIds = $user->clientes->pluck('cliente_endpoint_id');
    $clientesEndpointIds = DB::table('cliente_user as cu')
    ->join('clientes as c', 'c.id', '=', 'cu.cliente_id')
    ->select('c.cliente_endpoint_id')
    ->where('cu.user_id', $user->id)
    ->whereNull('cu.deleted_at')
    ->whereNull('c.deleted_at')
    ->orderBy('c.nombre','asc')
    ->get()
    ->map(function ($item) {
        return $item->cliente_endpoint_id;
    });
    
    $this->clearLoginAttempts($request);

    return response()->json([
        'access_token' => $token,
        'token_type' => 'Bearer',
        'clientes_endpoint_ids' => $clientesEndpointIds,
        'permissions' => $permissions,
        'reset_password' => $user->reset_password,
    ]);

} catch (Exception $e) {
    Log::error('Error en el proceso de login: ' . $e->getMessage());
    return response()->json(['title' => 'Error', 'message' => $e->getMessage()], 500);
}

}

     /**
     * Cierra la sesión de un usuario autenticado.
     *
     * @desc Este método elimina el token de acceso actual del usuario, cerrando su sesión activa.
     * @param Request $request La solicitud HTTP que contiene la sesión activa del usuario.
     * @return \Illuminate\Http\JsonResponse Respuesta confirmando el cierre de sesión.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada con éxito']);
    }


    /**
     * Verifica si un usuario ha excedido el número de intentos de inicio de sesión permitidos.
     *
     * @desc Utiliza el RateLimiter para controlar la cantidad de intentos fallidos de inicio de sesión.
     *       Si se excede el límite, se lanza una excepción.
     * @param Request $request La solicitud HTTP que contiene las credenciales de inicio de sesión.
     * @throws ValidationException Si se exceden los intentos permitidos.
     */

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

    /**
     * Genera un token para la recuperación de contraseña.
     *
     * @desc Valida la dirección de correo electrónico proporcionada, genera un token y lo almacena
     *       en la base de datos junto con la información del usuario.
     * @param Request $request La solicitud HTTP que contiene el correo electrónico del usuario.
     * @return \Illuminate\Http\JsonResponse Respuesta con el token de recuperación de contraseña.
     */

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

     /**
     * Verifica si un token de recuperación de contraseña es válido.
     *
     * @desc Este método valida el token proporcionado, verificando su existencia y si ha expirado.
     * @param string $token El token a verificar.
     * @return \Illuminate\Http\JsonResponse Respuesta que indica si el token es válido o no.
     */

    public function setVerificarToken($token)
    {
        try{
            $tokenResultado = TokensPassword::where('token', $token)->first();

            if (!$tokenResultado) {
                return response()->json(['success' => false, 'title' => 'Token no encontrado.', 'message' => 'El link con el cual entro es incorrecto.'], 404);
            }else {
                if (Carbon::now()->greaterThan($tokenResultado->expires_at)) {
                    return response()->json(['success' => false, 'title' => 'Token expirado.', 'message' => 'Por favor repita el proceso de recuperar contraseña.'], 403);
                }else {
                    return response()->json(['success' => true, 'message' => 'Token verificado.', 'id_username' => $tokenResultado->id_username], 200);
                }
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'title' => 'Error del servidor.', 'message' => 'Error del servidor a la hora de verificar el token.'], 500);
        }
    }

     /**
     * Verifica si el usuario actual está autenticado y tiene un token válido.
     *
     * @desc Revisa si el usuario tiene un token de sesión válido.
     * @param Request $request La solicitud HTTP del usuario autenticado.
     * @return \Illuminate\Http\JsonResponse Respuesta indicando si el usuario está autenticado.
     */

    public function setVerificarLogin(Request $request)
    {
        $token = $request->user()->tokens;

        if (!$token) {
            return response()->json(['success' => false], 404);
        }else {
            return response()->json(['success' => true], 200);
        }
    }

    /**
     * Elimina un token de recuperación de contraseña de la base de datos.
     *
     * @desc Este método borra el token de recuperación de contraseña asociado con el usuario.
     * @param string $token El token a eliminar.
     * @return \Illuminate\Http\JsonResponse Respuesta indicando el éxito o fallo del proceso.
     */

    public function deleteToken($token)
    {
        try {
            TokensPassword::where('token', $token)->delete();

            return response()->json(['success' => true, 'message' => 'Token exitosamente borrado.']);
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => 'Error a la hora de borrar el token.']);
        }
    }

    public function sendRecoveryEmail(Request $request)
    {
        try {
            // Validar los datos entrantes
            $validatedData = $request->validate([
                'email' => 'required|email',
                'token' => 'required|string',
            ]);

            $email = $validatedData['email'];
            $token = $validatedData['token'];
            $link = env('FRONTEND_URL') . "/actualizarPassword-{$token}";

            Mail::to($email)->send(new RecoverPasswordEmail($link,$email));

            return response()->json(['success' => true, 'message' => 'Por favor revise su correo para continuar con el proceso.'], 200);
        } catch (ValidationException $e) {
            // Si la validación falla, se capturan los errores y se devuelven
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de los datos del correo.',
                'errors' => $request
            ], 422);
        } catch (\Exception $e) {
            // Si ocurre cualquier otro error, devolver un error general
            return response()->json([
                'success' => false,
                'message' => 'Ha ocurrido un error al cargar.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Incrementa el contador de intentos fallidos de inicio de sesión para un usuario.
     *
     * @desc Utiliza el RateLimiter para incrementar el contador de intentos de login fallidos.
     * @param Request $request La solicitud HTTP con las credenciales del usuario.
     */

    protected function incrementLoginAttempts(Request $request)
    {
        RateLimiter::hit($this->throttleKey($request), 60);
    }
    
     /**
     * Limpia el contador de intentos fallidos de inicio de sesión tras un inicio exitoso.
     *
     * @desc Utiliza el RateLimiter para limpiar el registro de intentos fallidos de login.
     * @param Request $request La solicitud HTTP con las credenciales del usuario.
     */

    protected function clearLoginAttempts(Request $request)
    {
        RateLimiter::clear($this->throttleKey($request));
    }

     /**
     * Genera una clave única para el control de intentos de inicio de sesión por usuario.
     *
     * @desc Combina el nombre de usuario y la dirección IP para crear una clave única para cada usuario.
     * @param Request $request La solicitud HTTP con las credenciales del usuario.
     * @return string La clave de control para el RateLimiter.
     */

    protected function throttleKey(Request $request)
    {
        return strtolower($request->input('username')).'|'.$request->ip();
    }

    /**
     * Update the authenticated user's password because the previous one has expired.
     *
     * @desc This method validates the new password, encrypts it, and updates the user record.
     *       It also sets an expiration date for the password change; 
     *       the new date is one month after the password update.
     * @param Request $request The HTTP request containing the new password.
     * @return \Illuminate\Http\JsonResponse Response indicating the success or failure of the update.
     */
    public function updatePassword(Request $request)
    {
        try {
            // If encrypted_data is received, decrypt and extract the data.
            if ($request->has('encrypted_password')) {
                // Decrypt the encrypted password and the key to decrypt.
                $encryptedHex = $request->input('encrypted_password');
                $keyValue = env('KEY_ENCRYPTED');

                $decryptedData = $this->decryptAES($encryptedHex, $keyValue); // Decrypt the encrypted password and convert it to json format. 
                $credentials = json_decode($decryptedData, true); // Convert the decrypted data to an associative array.

                // Check if the JSON was decoded successfully.
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return response()->json(['title' => 'Error de desencriptado.', 'message' => 'No se pudo decodificar los datos desencriptados.'], 400);
                }

                // Overwrite the request values with the decrypted ones.
                $request->merge([
                    'password' => $credentials['password'] ?? null,
                ]);
            } else {
                return response()->json(['title' => 'Datos cifrados no proporcionados.', 'message' => 'No se recibió el campo encrypted_data.'], 400);
            }

            $user = Auth::user();
            if (!$user) {
                return response()->json(['title' => 'Usuario no autenticado.', 'message' => 'No se encontró el usuario autenticado.'], 404);
            }

            // Verify that the new password is not the same as the previous one.
            if (Hash::check($request->input('password'), $user->password)) {
                return response()->json(['title' => 'Contraseña repetida.', 'message' => 'La nueva contraseña no puede ser igual a la anterior.'], 409);
            }
            
            $user->password = Hash::make($request->input('password')); // Encrypts the new password.
            $user->reset_password = \Carbon\Carbon::now()->addMonth(); // Sets the reset password date to one month from now.
            $user->save();

            return response()->json(['title' => 'Actualización exitosa.', 'message' => 'Contraseña actualizada exitosamente.'], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['title' => 'Falta de campos.', 'message' => 'Faltan campos que llenar.', 'error' => $e->errors()], 406);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Fallo al actualizar.', 'message' => 'Error al actualizar la contraseña por expiración.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Decrypts the AES encrypted data.
     *
     * @desc This method decrypts the provided encrypted data using AES-256-CBC.
     *       It requires a key and an initialization vector (IV) for decryption.
     * @param string $encryptedHex The encrypted data in hexadecimal format.
     * @param string $keyValue The key used for decryption.
     * @return string The decrypted data.
     */
    protected function decryptAES($encryptedHex, $keyValue)
    {
        $salt = 'salt'; 
        $iterations = 100; 

        $ivHex = substr($encryptedHex, 0, 32);
        $cipherTextHex = substr($encryptedHex, 32); 

        $iv = hex2bin($ivHex);
        $cipherText = hex2bin($cipherTextHex);

        $key = hash_pbkdf2('sha256', $keyValue, $salt, $iterations, 32, true);

        $decrypted = openssl_decrypt($cipherText, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return $decrypted;
    }
}
