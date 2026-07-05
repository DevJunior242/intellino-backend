<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesTableSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'super_admin',
                'display_name' => 'Super Administrateur',
                'description' => 'Accès absolu à toute la plateforme (Toi seul).',
            ],
            [
                'name' => 'admin',
                'display_name' => 'Administrateur',
                'description' => 'Gestionnaire principal de la structure (Club, Ligue ou Fédération).',
            ],
            [
                'name' => 'dtn',
                'display_name' => 'Directeur Technique',
                'description' => 'Responsable des grades, des sélections et des stages.',
            ],
            [
                'name' => 'vice-president',
                'display_name' => 'Vice-Président',
                'description' => 'Assistant du directeur technique, responsable des grades, des sélections et des stages.',
            ],
            [
                'name' => 'secretaire',
                'display_name' => 'Secrétaire',
                'description' => 'Gestion administrative, convocations et suivi des dossiers.',
            ],
            [
                'name' => 'instructeur',
                'display_name' => 'Instructeur / Professeur',
                'description' => 'Gestion des cours, des fiches techniques et des présences sur le tatami.',
            ],
            [
                'name' => 'arbitre',
                'display_name' => 'Arbitre / Juge',
                'description' => 'Officiel convoqué pour superviser les compétitions.',
            ],
            [
                'name' => 'karateka',
                'display_name' => 'Karatéka / Parent',
                'description' => 'Élève ou tuteur. Accès aux licences, passeports et inscriptions.',
            ],
            [
                'name' => 'parent',
                'display_name' => ' Parent karateka',
                'description' => 'Élève ou tuteur. Accès aux licences, passeports et inscriptions.',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }
    }
}
