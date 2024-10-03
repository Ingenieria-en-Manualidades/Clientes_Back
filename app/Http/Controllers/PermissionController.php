<?php

namespace App\Http\Controllers;

use App\Models\User;
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

    public function guardarUserPermission(Request $request){
        try {
            // Validar los datos entrantes
            $validatedData = $request->validate([
                'user_id' => 'required|integer',
                'permission_id' => 'required|array',
            ]);

            // Guardar los datos en la base de datos
            $usuario = User::where('id', $validatedData['user_id'])->first();

            if ($usuario) {
                $usuario->permissions()->attach($validatedData['permission_id']);

                // Devolver una respuesta exitosa en caso de no fallar
                return response()->json(['success' => true,'message' => 'Relación usuario y permiso creado con éxito', 'data' => $request], 200);
            }else {
                return response()->json(['success' => false,'message' => 'Usuario no encontrado'], 200);
            }
        } catch (ValidationException $e) {
            // Si la validación falla, se capturan los errores y se devuelven
            return response()->json([
                'message' => 'Error en la validación de los datos de la relación usuario y permiso.',
                'errors' => $request
            ], 422);
        } catch (\Exception $e) {
            // Si ocurre cualquier otro error, devolver un error general
            return response()->json([
                'message' => 'Ha ocurrido un error al guardar la relación usuario y permiso.',
                'error' => $e->getMessage()
            ], 500);
        }
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
