<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $paliers = [
            'Club' => [
                ['name' => 'Club Découverte', 'description' => 'Pour démarrer', 'amount' => 0, 'min_users' => 0, 'max_users' => 30],
                ['name' => 'Club Starter', 'description' => 'Petit club en croissance', 'amount' => 15000, 'min_users' => 31, 'max_users' => 100],
                ['name' => 'Club Croissance', 'description' => 'Club établi', 'amount' => 35000, 'min_users' => 101, 'max_users' => 300],
                ['name' => 'Club Pro', 'description' => 'Grand club', 'amount' => 75000, 'min_users' => 301, 'max_users' => null],
            ],
            'Ligue' => [
                ['name' => 'Ligue Starter', 'description' => 'Ligue régionale', 'amount' => 50000, 'min_users' => 0, 'max_users' => 300],
                ['name' => 'Ligue Croissance', 'description' => 'Ligue en expansion', 'amount' => 120000, 'min_users' => 301, 'max_users' => 1000],
                ['name' => 'Ligue Pro', 'description' => 'Ligue majeure', 'amount' => 250000, 'min_users' => 1001, 'max_users' => 3000],
                ['name' => 'Ligue Sur-mesure', 'description' => 'Nous contacter', 'amount' => 0, 'min_users' => 3001, 'max_users' => null],
            ],
            'Federation' => [
                ['name' => 'Fédération Starter', 'description' => 'Fédération nationale', 'amount' => 150000, 'min_users' => 0, 'max_users' => 1000],
                ['name' => 'Fédération Croissance', 'description' => 'Fédération en expansion', 'amount' => 400000, 'min_users' => 1001, 'max_users' => 5000],
                ['name' => 'Fédération Pro', 'description' => 'Grande fédération', 'amount' => 900000, 'min_users' => 5001, 'max_users' => 15000],
                ['name' => 'Fédération Sur-mesure', 'description' => 'Nous contacter', 'amount' => 0, 'min_users' => 15001, 'max_users' => null],
            ],
        ];

        foreach ($paliers as $organisateurType => $plans) {
            foreach ($plans as $plan) {
                Plan::firstOrCreate(
                    ['name' => $plan['name']],
                    [...$plan, 'organisateur_type' => $organisateurType],
                );
            }
        }
    }
}
