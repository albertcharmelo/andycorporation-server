<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Lista de roles
        $roles_names = [
            'client',
            'delivery',
            'seller',
            'admin',
            'super_admin'
        ];

        // Crear roles si no existen
        foreach ($roles_names as $role_name) {
            Role::firstOrCreate(['name' => $role_name]);
        }

        // Permisos disponibles
        $permissions = [
            'view products',
            'create products',
            'edit products',
            'delete products',
            'manage orders',
            'assign deliveries',
            'view users',
            'edit users',
            'delete users',
            'manage roles',
        ];

        // Crear permisos si no existen
        foreach ($permissions as $permission_name) {
            Permission::firstOrCreate(['name' => $permission_name]);
        }

        // Asignar permisos a roles
        $roles_permissions_map = [
            'client' => [
                'view products',
            ],
            'delivery' => [
                'assign deliveries',
                'manage orders',
            ],
            'seller' => [
                'view products',
                'create products',
                'edit products',
                'delete products',
                'manage orders',
            ],
            'admin' => [
                'view products',
                'create products',
                'edit products',
                'delete products',
                'manage orders',
                'view users',
                'edit users',
            ],
            'super_admin' => $permissions, // todos
        ];

        // Asignar permisos a cada rol
        foreach ($roles_permissions_map as $role_name => $perms) {
            $role = Role::where('name', $role_name)->first();

            foreach ($perms as $perm) {
                $permission = Permission::where('name', $perm)->first();
                if (!$role->hasPermissionTo($perm)) {
                    $role->givePermissionTo($permission);
                }
            }
        }
        ## Usar el guard de api
        Role::query()->update(['guard_name' => 'api']);
        Permission::query()->update(['guard_name' => 'api']);
    }
}
