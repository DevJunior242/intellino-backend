<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Grandfathers les comptes créés avant l'introduction de la vérification d'email :
     * sans ça, le middleware 'verified' bloquerait immédiatement tous les utilisateurs
     * existants (qui n'ont jamais eu de lien à cliquer).
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        // Non réversible intentionnellement : on ne veut pas re-verrouiller
        // des comptes existants qui utilisent déjà l'app normalement.
    }
};
