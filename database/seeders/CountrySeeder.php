<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            'Burkina Faso',
            'Guinée',
            'Mali',
            'Niger',
            'Côte d\'Ivoire',
            'Tchad',
            'Algérie',
            'Maroc',
            'Tunisie',
            'Egypte',
            'Libye',
            'Maroc',
            'Mauritanie',
            'Sénégal',
            'Tunisie',
            'Algérie',
            'Tchad',
            'Egypte',
            'Libye',
            'Maroc',
            'Mauritanie'
        ];
        foreach ($countries as $country) {
            \App\Models\Country::firstOrCreate(
                ['name' => $country]
            );
        }
    }
}
