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
        Schema::table('ordre_passages', function (Blueprint $table) {
            // Le kata est choisi par tour (Art. 6.1 WKF), pas une fois pour
            // toute la compétition : on le rattache au passage, pas à
            // l'inscription.
            $table->foreignUuid('kata_id')
                ->nullable()
                ->after('inscription_id')
                ->constrained('katas')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ordre_passages', function (Blueprint $table) {
            $table->dropForeign(['kata_id']);
            $table->dropColumn('kata_id');
        });
    }
};
