<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class GradeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //         Ceinture blanche (débutant)
        // Ceinture jaune
        // Ceinture orange
        // Ceinture verte
        // Ceinture bleue
        // Ceinture marron
        // Ceinture noire
        $grades = [
            'centure blanche',
            'centure jaune',
            'centure orange',
            'centure verte',
            'centure bleue',
            'centure marron',
            'centure noire',
        ];
        foreach ($grades as $grade) {
            \App\Models\Grade::firstOrCreate(
                ['name' => $grade]
            );
        }
    }
}
