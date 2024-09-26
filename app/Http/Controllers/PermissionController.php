<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $permissions = Permission::all();
        return view('permissions.index', compact('permissions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
{
    $permission = Permission::findById($id);

    $request->validate([
        'name' => 'required|unique:permissions,name,' . $id
    ]);

    $name = htmlspecialchars($request->name, ENT_QUOTES, 'UTF-8');
    $permission->update(['name' => $name]);

    return redirect()->route('permisos.index')->with('success', 'Permisos actualizado con éxito.');
}


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $permission = Permission::find($id);
        if ($permission) {
            $permission->delete();
            return redirect()->route('permisos.index')->with('success', 'Permiso Eliminado Con Éxito.');
        } else {
            return redirect()->route('permisos.index')->with('error', 'El Permiso no se pudo encontrar.');
        }
    }
}
