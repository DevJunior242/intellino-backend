<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Database\Seeders\GradeSeeder;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\JugeOptionSeeder;
use Database\Seeders\ModeSaisieSeeder;
use Database\Seeders\RolesTableSeeder;
use Database\Seeders\PaymentCategorySeeder;
use Database\Seeders\NiveauCompetitionSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Database\Seeders\CountrySeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
        $this->call([
            RolesTableSeeder::class,
            PaymentCategorySeeder::class,
            JugeOptionSeeder::class,
            ModeSaisieSeeder::class,
            DisciplineSeeder::class,
            NiveauCompetitionSeeder::class,
            GradeSeeder::class,
            CountrySeeder::class,

        ]);
    }
}
