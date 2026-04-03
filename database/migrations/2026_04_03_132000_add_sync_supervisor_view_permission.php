<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'sync.supervisor.view', 'guard_name' => 'web'],
            ['created_at' => now(), 'updated_at' => now()]
        );

        $legacyPermissionId = DB::table('permissions')
            ->where('name', 'register_report.view')
            ->where('guard_name', 'web')
            ->value('id');

        if (!empty($legacyPermissionId) && !empty($permission->id)) {
            $roleIds = DB::table('role_has_permissions')
                ->where('permission_id', $legacyPermissionId)
                ->pluck('role_id');

            foreach ($roleIds as $roleId) {
                $exists = DB::table('role_has_permissions')
                    ->where('permission_id', $permission->id)
                    ->where('role_id', $roleId)
                    ->exists();

                if (!$exists) {
                    DB::table('role_has_permissions')->insert([
                        'permission_id' => $permission->id,
                        'role_id' => $roleId,
                    ]);
                }
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'sync.supervisor.view')
            ->where('guard_name', 'web')
            ->value('id');

        if (!empty($permissionId)) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
