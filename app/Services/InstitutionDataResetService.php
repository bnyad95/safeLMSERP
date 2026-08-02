<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class InstitutionDataResetService
{
    /**
     * @return array{cleared_tables: int, preserved_administrators: int, deleted_users: int}
     */
    public function clear(): array
    {
        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Institution data can only be cleared from a MySQL or MariaDB database.');
        }

        $superAdministratorIds = DB::table('users')
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.name', 'super_administrator')
            ->distinct()
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($superAdministratorIds === []) {
            throw new RuntimeException('No Super Administrator account was found. No data was deleted.');
        }

        $protectedTables = [
            'migrations',
            'users',
            'roles',
            'permissions',
            'role_user',
            'permission_role',
            'permission_user',
        ];

        $databaseName = DB::connection()->getDatabaseName();
        $tables = collect(DB::select(
            <<<'SQL'
                SELECT TABLE_NAME AS table_name
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'
                ORDER BY TABLE_NAME
            SQL,
            [$databaseName]
        ))
            ->pluck('table_name')
            ->reject(fn (string $table) => in_array($table, $protectedTables, true))
            ->values();

        $deletedUsers = DB::table('users')
            ->whereNotIn('id', $superAdministratorIds)
            ->count();

        DB::beginTransaction();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($tables as $table) {
                DB::table($table)->delete();
            }

            DB::table('role_user')
                ->whereNotIn('user_id', $superAdministratorIds)
                ->delete();

            DB::table('permission_user')
                ->whereNotIn('user_id', $superAdministratorIds)
                ->delete();

            DB::table('users')
                ->whereNotIn('id', $superAdministratorIds)
                ->delete();

            DB::table('users')
                ->whereIn('id', $superAdministratorIds)
                ->update([
                    'university_id' => null,
                    'college_id' => null,
                    'department_id' => null,
                    'account_blocked_at' => null,
                    'account_blocked_by' => null,
                    'account_block_reason' => null,
                    'account_block_type' => null,
                    'remember_token' => null,
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);

            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            DB::commit();
        } catch (Throwable $exception) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            DB::rollBack();

            throw $exception;
        }

        return [
            'cleared_tables' => $tables->count(),
            'preserved_administrators' => count($superAdministratorIds),
            'deleted_users' => $deletedUsers,
        ];
    }
}
