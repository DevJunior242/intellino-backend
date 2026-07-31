<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->foreignUuid('kata_id')->nullable()->after('athlete_id')
                ->constrained('katas')->nullOnDelete();
            $table->dropColumn('kata');
        });
    }

    public function down(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kata_id');
            $table->string('kata')->nullable();
        });
    }
};
