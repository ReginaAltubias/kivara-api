<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UpdateCoordinatorPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'teachers.manage',
            'students.manage',
            'classrooms.manage',
            'challenges.assign',
            'reports.school'
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // Assign these to the Coordinator role if it exists, otherwise create it
        $role = Role::firstOrCreate(['name' => 'coordenador']);
        
        // Atribui as permissões ao coordenador
        $role->givePermissionTo($permissions);
    }
}