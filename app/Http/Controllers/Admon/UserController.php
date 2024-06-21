<?php

namespace App\Http\Controllers\Admon;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Cliente;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;


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
            'nombre' => 'required|string|max:15',
            'rol' => 'required|exists:roles,id',
            'password' => 'nullable|string|min:8|confirmed',
            'clientes' => 'array',
            'clientes.*' => 'exists:clientes,id',
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $user = User::findOrFail($id);

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
        $roles = Role::all();
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
}
