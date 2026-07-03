<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            // Tête de série manuelle (1 = meilleure), facultative.
            // Si non renseignée, le tirage reste aléatoire pour ce combattant.
            $table->unsignedInteger('seed')->nullable()->after('statut_passage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->dropColumn('seed');
        });
    }
};
