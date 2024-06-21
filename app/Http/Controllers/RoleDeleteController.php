<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;
use Illuminate\Support\Facades\Log; 

class RoleDeleteController extends Controller
{
    /**
     * Elimina el rol especificado.
     *
     * @desc Este método busca un rol por su ID y lo elimina si existe.
     * @param int $id El ID del rol a eliminar.
     * @return \Illuminate\Http\RedirectResponse Redirige a la lista de roles con un mensaje de éxito o error.
     */
    public function destroy(int $id)
    {
        $role = Role::find($id);
        if ($role) {
            $role->delete();
            return redirect()->route('roles.index')->with('success', 'Rol Eliminado Con Éxito.');
        } else {
            return redirect()->route('roles.index')->with('error', 'El rol no se pudo encontrar.');
        }
    }
}
