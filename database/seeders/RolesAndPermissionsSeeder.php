<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Permission::create(['name' => 'ver clientes', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'ver roles', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'ver usuarios', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'asignar permisos', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'activar/desactivar clientes', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'Crear Usuarios', 'guard_name' => 'sanctum']);

        // Crear rol de administrador y asignar permisos
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'sanctum']);
        $adminRole->givePermissionTo(['ver clientes', 'asignar permisos', 'activar/desactivar clientes', 'Crear Usuarios']);
    }
}
