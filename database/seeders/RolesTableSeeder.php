<?php

namespace Database\Seeders;

use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesTableSeeder extends Seeder
{
    public function run()
    {
        if (DB::table('roles')->count() == 0) {
            DB::table('roles')->insert([
                [
                    'id' => (string) Str::uuid(),
                    'name' => 'super_admin',
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'id' => (string) Str::uuid(),
                    'name' => 'admin_club',
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'id' => (string) Str::uuid(),
                    'name' => 'admin_league',
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'id' => (string) Str::uuid(),
                    'name' => 'instructeur',
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'id' => (string) Str::uuid(),
                    'name' => 'secretaire',
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'id' => (string) Str::uuid(),
                    'name' => 'parent',
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'id' => (string) Str::uuid(),
                    'name' => 'etudiant',
                    'created_at' => now(),
                    'updated_at' => now()
                ],
            ]);
        }
    }
}
