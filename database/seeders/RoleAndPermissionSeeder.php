<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Seed roles and permissions for Admin Global and B2B (Schools).
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = config('auth.defaults.guard', 'web');

        $permissions = [
            'schools.view',
            'schools.create',
            'schools.update',
            'schools.delete',
            'schools.approve',
            'classrooms.view',
            'classrooms.create',
            'classrooms.update',
            'classrooms.delete',
            'students.view',
            'students.create',
            'students.update',
            'students.delete',
            'teachers.view',
            'teachers.create',
            'teachers.update',
            'teachers.delete',
            'missions.assign',
            'missions.approve',
            'reports.school',
            'reports.classroom',
            'finance.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => $guard,
            ]);
        }

        $superAdmin = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => $guard,
        ]);
        $superAdmin->syncPermissions(Permission::where('guard_name', $guard)->get());

        $coordinator = Role::firstOrCreate([
            'name' => 'coordinator',
            'guard_name' => $guard,
        ]);
        $coordinator->syncPermissions([
            'classrooms.view',
            'classrooms.create',
            'classrooms.update',
            'classrooms.delete',
            'students.view',
            'students.create',
            'students.update',
            'students.delete',
            'teachers.view',
            'teachers.create',
            'teachers.update',
            'teachers.delete',
            'reports.school',
        ]);

        $teacher = Role::firstOrCreate([
            'name' => 'teacher',
            'guard_name' => $guard,
        ]);
        $teacher->syncPermissions([
            'classrooms.view',
            'students.view',
            'missions.assign',
            'missions.approve',
            'reports.classroom',
        ]);
    }
}
