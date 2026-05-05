<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DisciplineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $disciplines = [
            'Karaté',
            'Judo'
        ];
        foreach ($disciplines as $discipline) {
            \App\Models\Discipline::firstOrCreate(
                ['name' => $discipline]
            );
        }
    }
}
