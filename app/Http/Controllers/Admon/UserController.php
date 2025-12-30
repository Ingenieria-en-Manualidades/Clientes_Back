<?php

namespace App\Http\Controllers\Admon;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Cliente;
use App\Models\TokensPassword;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\survey\CustomerContact;
use App\Http\Controllers\PermissionController;


class UserController extends Controller
{

    /**
     * Muestra una lista de los usuarios, roles y clientes.
     *
     * @desc Este método obtiene todos los usuarios, roles y clientes y los pasa a la vista de usuarios.
     * @param Request $request La solicitud HTTP.
     * @return View La vista con la lista de usuarios, roles y clientes.
     */
    public function index(Request $request): View
    {
        $usuarios = User::all();
        $roles = Role::all();
        $clientes = Cliente::all();

        return view('user.index', [
            'usuarios' => $usuarios,
            'roles' => $roles,
            'clientes' => $clientes,
        ]);
    }

    /**
     * Almacena un nuevo usuario en la base de datos.
     *
     * @desc Este método valida y crea un nuevo usuario con los roles y clientes especificados.
     * @param Request $request La solicitud HTTP que contiene los datos del nuevo usuario.
     * @return \Illuminate\Http\RedirectResponse Redirige a la vista anterior con un mensaje de éxito o error.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'rol' => 'required|exists:roles,id',
            'password' => 'required|string|min:8|confirmed',
            'clientes' => 'array',
            'clientes.*' => 'exists:clientes,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $nombre = htmlspecialchars($request->nombre, ENT_QUOTES, 'UTF-8');

            $user = new User();
            $user->name = $nombre;
            $user->password = Hash::make($request->password);

            // Generar un correo electrónico aleatorio y asegurarse de que no exista
            do {
                $user->email = 'user' . rand(1000, 9999) . '@example.com';
            } while (User::where('email', $user->email)->exists());

            $user->save();

            $user->roles()->sync([$request->rol]);
            $user->clientes()->sync($request->clientes);

            return redirect()->back()->with('success', 'Usuario creado exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al crear el usuario: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Hubo un error al crear el usuario: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Store a new user in the database.
     *
     * @desc This method validates and creates a new user with the specified permissions and clients.
     * @param Request $request The HTTP request that contains the new user's data.
     * @return \Illuminate\Http\RedirectResponse Redirects to the previous view with a success or error message.
     */
    public function storeFrontend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userType' => 'required|string|in:employee,client',
            'employee_id' => 'nullable|string',
            'fullname' => 'required|string|max:255',
            'username' => 'required|string|max:255',
            'cellphone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'clients' => 'required|array',
            'clients.*' => [
                'integer',
                Rule::when(
                    fn () => !in_array("0", (array) $request->input('clients', []), true),
                    'exists:clientes,cliente_endpoint_id'
                ),
            ],
            'rol' => 'required|exists:roles,id',
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id',
            'creator_user' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['title' => 'Error de validación.', 'message' => $validator->errors(), 'error' => $validator->errors()], 422);
        }

        if ($request->userType === 'employee' && $request->filled('employee_id')) {
            $userByEmployee = User::where('empleado_id', $request->employee_id)->first();
            if ($userByEmployee) {
                return response()->json(['title' => 'Empleado con usuario.', 'message' => "El empleado seleccionado ya tiene un usuario asignado con el nombre de usuario '{$userByEmployee->name}'."], 409);
            }
        }

        // 2. Check if a user with the same username already exists.
        $userByUsername = User::where('name', $request->username)->first();
        if ($userByUsername) {
            return response()->json(['title' => 'Nombre de usuario existente.', 'message' => "Ya existe un usuario con el nombre de usuario '{$request->username}'."], 409);
        }

        DB::beginTransaction();
        try {
            $user = new User();
            $user->name = $request->username;
            $user->email = $request->email;
            $user->password = Hash::make($request->password);

            if ($request->userType === 'employee') {
                $user->empleado_id = $request->employee_id;
            }
            $user->save();

            // 3. If it is a customer, create "CustomerContact".
            if ($request->userType === 'client') {
                $customerContact = new CustomerContact();
                $customerContact->fullname = $request->fullname;
                $customerContact->cellphone = $request->cellphone;
                $customerContact->email = $request->email;
                $customerContact->user_id = $user->id;
                $customerContact->username = $request->creator_user;
                $customerContact->save();
            }

            // 4. Connect users with customers.
            $clients = (array) $request->input('clients', []);

            if (in_array("0", $clients, true)) {
                // 0 => relate to all existing customers.
                $clientsIds = Cliente::query()->pluck('id');
            } else {
                // relate only those selected by endpoint_id.
                $clientsIds = Cliente::query()->whereIn('cliente_endpoint_id', $clients)->pluck('id');
            }
            $user->clientes()->sync($clientsIds);

            // 5. Add role to user.
            $user->roles()->sync([$request->rol]);

            // 6. Link user with permissions.
            $user->permissions()->sync($request->permissions);

            DB::commit();
            return response()->json(['title' => 'Exito.', 'message' => 'Usuario creado exitosamente.'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en storeFrontend: ' . $e->getMessage());
            return response()->json(['title' => 'Error de servidor.', 'message' => $e->getMessage(), 'error' => $e->getMessage()], 500);
        }
    }
    
    public function getUsers() 
    {
        try {
            $users = User::leftjoin('public.empleado as e', 'e.empleado_id', '=', 'users.empleado_id')
            ->leftjoin('surveys.customer_contact as cc', 'cc.user_id', '=', 'users.id')
            ->select('users.*', 
                DB::raw("CONCAT(e.nombre, ' ', e.apellido) AS fullname"), 'e.nro_documento AS num_document', 'e.celular as cellphone', 
                'cc.fullname as fullname_client', 'cc.cellphone as cellphone_client', 'cc.email as email_client'
            )
            ->get();

            return response()->json(['data' => $users], 200);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error de servidor.', 'message' => $e->getMessage(), 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Actualiza un usuario existente en la base de datos.
     *
     * @desc Este método valida y actualiza un usuario existente con los nuevos datos y roles especificados.
     * @param Request $request La solicitud HTTP que contiene los datos actualizados del usuario.
     * @param int $id El ID del usuario a actualizar.
     * @return \Illuminate\Http\RedirectResponse Redirige a la vista anterior con un mensaje de éxito o error.
     */
    public function update(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:30',
            'rol' => 'required|exists:roles,id',
            'password' => 'nullable|string|min:8|confirmed',
            'clientes' => 'array',
            'clientes.*' => 'exists:clientes,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $user = User::findOrFail($id);
        //sanitizacion
        $nombre = htmlspecialchars($request->nombre, ENT_QUOTES, 'UTF-8');
        $user->name = $nombre;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        $user->roles()->sync([$request->rol]);
        $user->clientes()->sync($request->clientes);

        return redirect()->back()->with('success', 'Usuario actualizado exitosamente');
    }

    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|string|min:1',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Faltan campos que llenar.'], 406);
        }

        $id = $request->id;
        $user = User::findOrFail($id);

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado en la base de datos.'], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        TokensPassword::where('id_username', $id)->delete();

        return response()->json(['success' => true, 'message' => 'Contraseña actualizada exitosamente.'], 200);
    }

    /**
     * Elimina el usuario especificado del almacenamiento.
     *
     * @desc Este método elimina un usuario específico de la base de datos.
     * @param int $id El ID del usuario a eliminar.
     * @return \Illuminate\Http\RedirectResponse Redirige a la vista anterior con un mensaje de éxito.
     */
    public function destroy(int $id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return redirect()->back()->with('success', 'Usuario borrado correctamente.');
    }

    /**
     * Muestra una lista de los usuarios deshabilitados.
     *
     * @desc Este método obtiene todos los usuarios deshabilitados (eliminados softdeletes) y los devuelve como respuesta JSON.
     * @return \Illuminate\Http\JsonResponse
     */
    public function deshabilitados()
    {
        $usuarios = User::onlyTrashed()->get();
        return response()->json($usuarios);
    }

    /**
     * Restaura un usuario deshabilitado.
     *
     * @desc Este método restaura un usuario previamente deshabilitado (eliminado softdeletes).
     * @param int $id El ID del usuario a restaurar.
     * @return \Illuminate\Http\RedirectResponse Redirige a la lista de usuarios con un mensaje de éxito.
     */
    public function restaurar(int $id)
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();

        return redirect()->route('usuarios.index')->with('success', 'Usuario restaurado exitosamente.');
    }

    /**
     * Envia los clientes y roles a un js.
     *
     * @desc Este método sirve para pasarle a al js usuarios de manera más dinámica.
     * @return \Illuminate\Http\JsonResponse con la info de todos clientes y roles.
     */
    public function getRolesAndClientes()
    {
        $roles = Role::with('permissions')->whereNull('deleted_at')->get();
        $clientes = Cliente::all();

        return response()->json([
            'roles' => $roles,
            'clientes' => $clientes,
        ]);
    }

    /**
     * Alterna el estado activo/inactivo de un usuario.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleActive(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        try {
            $user->activo = $user->activo === 's' ? 'n' : 's';
            $user->save();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Ocurrió un error al actualizar el estado del usuario.']);
        }
    }

    public function resetUser(int $id)
    {
        try {
            $newPassword = "Temporal01*";
            
            $user = User::findOrFail($id);

            // Coloca la fecha anterior a la actual (ayer)
            $user->reset_password = \Carbon\Carbon::now()->subDay();
            $user->password = \Illuminate\Support\Facades\Hash::make($newPassword);

            if ($user->save()) {
                return response()->json(['success' => true, 'title' => 'El usuario ha sido restablecido.', 'message' => 'La nueva contraseña es "Temporal01*".'], 200);
            } else {
                return response()->json(['success' => false, 'title' => 'Fallo al restablecer el usuario.', 'message' => 'No se pudo restablecer el usuario.'], 500);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'title' => 'Error desconocido al restablecer el usuario.', 'message' => $e->getMessage(), 'error' => $e->getMessage()], 500);
        }
    }

    public function getEmployeesImecByClientsId(int $clients_id, Request $request)
    {
        try {
            $token = $request->header(config('app.type_key_app_clients'));

            $expectedToken = config('app.api_key_app_clients'); // Token predefinido

            // Check if the token in the request matches the predefined token.
            if ($token !== $expectedToken) {
                return response()->json(['success' => 'error', 'title' => 'Token no válido', 'message' => 'Error en la petición al enviar el token incorrecto'], 401);
            }

            $employees = DB::table('public.empleado as e')
            ->join('public.contrato as c', 'c.empleado_id', '=', 'e.empleado_id')
            ->select(
                'e.empleado_id',
                'e.email',
                'e.celular',
                DB::raw("CONCAT(e.nombre, ' ', e.apellido) AS nombre"),
                DB::raw("CONCAT(e.nro_documento, ' ',e.nombre, ' ', e.apellido) AS nombre_completo")
            );
            if ($clients_id != 0) {
                $employees = $employees->where('c.cliente_id', $clients_id);
            }
            $employees = $employees->whereNull('c.deleted_at')
            ->whereNull('e.deleted_at')
            ->orderBy('e.nombre','asc')
            ->get();
            
            return response()->json(['data' => $employees], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'title' => 'Error al retornar empleados.', 'message' => $e->getMessage(), 'error' => $e->getMessage()], 500);
        }
    }

    public function getRoles()
    {
        try {
            $roles = Role::with('permissions')->whereNull('deleted_at')->get();

            return response()->json(['data' => $roles], 200);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error de servidor.', 'message' => $e->getMessage(), 'error' => $e->getMessage()], 500);
        }
    }
}
