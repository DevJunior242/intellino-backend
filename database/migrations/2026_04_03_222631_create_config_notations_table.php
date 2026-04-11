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
        Schema::create('config_notations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('competition_id')
                ->constrained('competitions')->cascadeOnDelete();
            $table->foreignUuid('plateau_id')
                ->constrained('plateaux')->cascadeOnDelete();
            //kumiteformat_id 
            $table->foreignUuid('kumite_format_id')
                ->nullable()
                ->constrained('kumite_formats')->nullOnDelete();
            $table->foreignUuid('mode_saisie_id')
                ->constrained('mode_saisies')->cascadeOnDelete();
            $table->foreignUuid('nb_juges_option_id')->nullable()
                ->constrained('juge_options')->cascadeOnDelete();
            $table->integer('nb_rotation')
                ->nullable()
                ->default(1)
            ;
            $table->integer('duration')->nullable()->default(180);
            $table->boolean('configuration_validee')->default(false);
            $table->foreignUuid('configure_par')->constrained('users');
            $table->timestamp('validee_a')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('config_notations');
    }
};
