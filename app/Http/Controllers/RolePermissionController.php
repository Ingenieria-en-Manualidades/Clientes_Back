<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionController extends Controller
{
    /**
     * Muestra una lista de los roles con sus permisos.
     *
     * @desc Este método obtiene todos los roles con sus permisos asociados y los pasa a la vista de index de roles.
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $roles = Role::with('permissions')->whereNull('deleted_at')->get();
        return view('roles.index', compact('roles'));
    }

    /**
     * Muestra el formulario para crear un nuevo rol.
     *
     * @desc Este método obtiene todos los permisos y los pasa a la vista de creación de roles.
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $permissions = Permission::all();
        return view('roles.create', compact('permissions'));
    }

    /**
     * Almacena un nuevo rol en la base de datos.
     *
     * @desc Este método valida y crea un nuevo rol con los permisos especificados.
     * @param Request $request La solicitud HTTP que contiene los datos del nuevo rol.
     * @return \Illuminate\Http\RedirectResponse Redirige a la lista de roles con un mensaje de éxito.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:roles,name',
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $name = htmlspecialchars($request->name, ENT_QUOTES, 'UTF-8');

        $role = Role::create(['name' => $name]);
        $role->givePermissionTo($request->permissions);

        return redirect()->route('roles.index')->with('success', 'Rol creado con éxito.');
    }

    /**
     * Actualiza un rol existente en la base de datos.
     *
     * @desc Este método valida y actualiza un rol existente con los nuevos datos y permisos especificados.
     * @param Request $request La solicitud HTTP que contiene los datos actualizados del rol.
     * @param int $id El ID del rol a actualizar.
     * @return \Illuminate\Http\RedirectResponse Redirige a la lista de roles con un mensaje de éxito.
     */
    public function update(Request $request, int $id)
    {
        $role = Role::findById($id);

        $request->validate([
            'name' => 'required|unique:roles,name,' . $role->id,
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $name = htmlspecialchars($request->name, ENT_QUOTES, 'UTF-8');

        $role->update(['name' => $name]);

        // Sincronizar los permisos (nos aseguramos de pasar un array vacío si no hay permisos)
        $permissions = $request->has('permissions') ? $request->permissions : [];
        $role->syncPermissions($permissions);

        return redirect()->route('roles.index')->with('success', 'Rol actualizado con éxito.');
    }

    /**
     * Muestra una lista de los roles deshabilitados.
     *
     * @desc Este método obtiene todos los roles deshabilitados (eliminados lógicamente) y los devuelve como respuesta JSON.
     * @return \Illuminate\Http\JsonResponse
     */
    public function deshabilitados()
    {
        $roles = Role::onlyTrashed()->get();
        return response()->json($roles);
    }

    /**
     * Restaura un rol deshabilitado.
     *
     * @desc Este método restaura un rol previamente deshabilitado (eliminado lógicamente).
     * @param int $id El ID del rol a restaurar.
     * @return \Illuminate\Http\RedirectResponse Redirige a la lista de roles con un mensaje de éxito.
     */
    public function restaurar(int $id)
    {
        $role = Role::withTrashed()->findOrFail($id);
        $role->restore();
        return redirect()->route('roles.index')->with('success', 'Rol restaurado con éxito.');
    }

    /**
     * Muestra una lista de los roles con sus permisos.
     *
     * @desc Este método obtiene todos los roles con sus permisos asociados y los pasa al js de roles.
     * @return \Illuminate\View\View
     */
    public function getRoles()
    {
        $roles = Role::with('permissions')->get();  // Incluye los permisos relacionados con los roles
        $permissions = Permission::all();  // Obtén todos los permisos

        // Devuelve un JSON que contenga tanto los roles como los permisos
        return response()->json([
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function getRolesFrontend()
    {
        try {
            $roles = Role::with('permissions:id,name')
                ->whereNull('deleted_at')
                ->orderBy('name', 'asc')
                ->get()
                ->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'permissions' => $role->permissions->map(function ($permission) {
                            return [
                                'id' => $permission->id,
                                'name' => $permission->name,
                            ];
                        })->values(),
                        'permissions_count' => $role->permissions->count(),
                    ];
                });

            return response()->json(['data' => $roles], 200);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => 'Ha ocurrido un error al listar los roles.', 'error' => $e->getMessage()], 500);
        }
    }

    public function getDisabledRolesFrontend()
    {
        try {
            $roles = Role::with('permissions:id,name')
                ->whereNotNull('deleted_at')
                ->orderBy('name', 'asc')
                ->get()
                ->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'permissions' => $role->permissions->map(function ($permission) {
                            return [
                                'id' => $permission->id,
                                'name' => $permission->name,
                            ];
                        })->values(),
                        'permissions_count' => $role->permissions->count(),
                    ];
                });

            return response()->json(['data' => $roles], 200);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => 'Ha ocurrido un error al listar los roles inhabilitados.', 'error' => $e->getMessage()], 500);
        }
    }

    public function storeFrontend(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255|unique:roles,name',
                'permissions' => 'nullable|array',
                'permissions.*' => 'integer|exists:permissions,id',
            ]);

            $role = Role::create([
                'name' => htmlspecialchars($validatedData['name'], ENT_QUOTES, 'UTF-8'),
                'guard_name' => 'sanctum',
            ]);

            $permissionNames = Permission::whereIn('id', $validatedData['permissions'] ?? [])->pluck('name')->toArray();
            $role->syncPermissions($permissionNames);

            return response()->json(['title' => 'Rol creado.', 'message' => 'Rol creado correctamente.'], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['title' => 'Error de validación.', 'message' => 'Error en la validación del rol.', 'error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => 'Ha ocurrido un error al crear el rol.', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateFrontend(Request $request, int $id)
    {
        try {
            $role = Role::findById($id, 'sanctum');

            $validatedData = $request->validate([
                'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
                'permissions' => 'nullable|array',
                'permissions.*' => 'integer|exists:permissions,id',
            ]);

            $role->update([
                'name' => htmlspecialchars($validatedData['name'], ENT_QUOTES, 'UTF-8'),
            ]);

            $permissionNames = Permission::whereIn('id', $validatedData['permissions'] ?? [])->pluck('name')->toArray();
            $role->syncPermissions($permissionNames);

            return response()->json(['title' => 'Rol actualizado.', 'message' => 'Rol actualizado correctamente.'], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['title' => 'Error de validación.', 'message' => 'Error en la validación del rol.', 'error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => 'Ha ocurrido un error al actualizar el rol.', 'error' => $e->getMessage()], 500);
        }
    }

    public function disableFrontend(int $id)
    {
        try {
            $role = Role::where('id', $id)->whereNull('deleted_at')->firstOrFail();

            DB::table('roles')->where('id', $role->id)->update([
                'activo' => 'n',
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json(['title' => 'Rol inhabilitado.', 'message' => 'Rol inhabilitado correctamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => 'Ha ocurrido un error al inhabilitar el rol.', 'error' => $e->getMessage()], 500);
        }
    }

    public function restoreFrontend(int $id)
    {
        try {
            $role = Role::where('id', $id)->whereNotNull('deleted_at')->firstOrFail();

            DB::table('roles')->where('id', $role->id)->update([
                'activo' => 's',
                'deleted_at' => null,
                'updated_at' => now(),
            ]);

            return response()->json(['title' => 'Rol habilitado.', 'message' => 'Rol habilitado correctamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => 'Ha ocurrido un error al habilitar el rol.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * crea un permiso para asignar a un  rol
     *
     * @param Request $request La solicitud HTTP que contiene los datos del nuevo rol.
     * @return \Illuminate\Http\RedirectResponse Redirige a la lista de roles con un mensaje de éxito
     */

    public function storePermission(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:permissions,name',
        ]);

        Permission::create(['name' => $request->name]);

        return redirect()->back()->with('success', 'Permiso creado con éxito.');
    }
}
