<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordre_passages', function (Blueprint $table) {
            $table->dropColumn('score_final');
        });
    }

    public function down(): void
    {
        Schema::table('ordre_passages', function (Blueprint $table) {
            $table->float('score_final')->nullable();
        });
    }
};
