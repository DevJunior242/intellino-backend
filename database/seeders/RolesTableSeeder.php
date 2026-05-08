<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesTableSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            'super_admin',
            'admin_club',
            'admin_league',
            'instructeur',
            'secretaire',
            'parent',
            'karateka',
            'arbitre_league',
        ];

        foreach ($roles as $name) {
            foreach ($roles as $name) {
                Role::firstOrCreate(
                    ['name' => $name]
                );
            }
        }
    }
}
