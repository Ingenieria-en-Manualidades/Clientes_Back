<?php

namespace App\Http\Controllers\Admon;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Cliente;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    /**
     * Muestra el dashboard con los contadores de clientes activos, usuarios activos y roles.
     *
     * @desc Este método obtiene los contadores de clientes activos, usuarios activos y roles de la base de datos y los pasa a la vista del dashboard.
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Contar los clientes activos
        $activeClients = Cliente::all()->count();
        // Contar los usuarios activos
        $activeUsers = User::all()->count();
        // Contar los roles
        $rolesCount = Role::count();
        // Retornar la vista del dashboard con los datos contados
        return view('dashboard', compact('activeClients', 'activeUsers', 'rolesCount'));
    }
}
