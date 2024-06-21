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
        Permission::create(['name' => 'ver clientes']);
        Permission::create(['name' => 'ver roles']);
        Permission::create(['name' => 'ver usuarios']);
        Permission::create(['name' => 'asignar permisos']);
        Permission::create(['name' => 'activar/desactivar clientes']);
        Permission::create(['name' => 'Crear Usuarios']);

        // Crear rol de administrador y asignar permisos
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo(['ver clientes', 'asignar permisos', 'activar/desactivar clientes', 'Crear Usuarios']);
    }
}
