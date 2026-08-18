<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_categories', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->foreignUuid('club_id')->nullable()->after('id')->constrained('clubs')->cascadeOnDelete();
            $table->unique(['slug', 'club_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_categories', function (Blueprint $table) {
            $table->dropUnique(['slug', 'club_id']);
            $table->dropConstrainedForeignId('club_id');
            $table->unique(['slug']);
        });
    }
};
