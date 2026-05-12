<?php

namespace Database\Seeders;

use App\Models\Grade;
use Illuminate\Database\Seeder;

class GradeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
            Grade::firstOrCreate(
                ['name' => $grade]
            );
        }
    }
}
