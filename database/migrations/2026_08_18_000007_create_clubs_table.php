<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clubs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name');
            $table->string('logo')->nullable();
            $table->string('city');
            $table->string('region');
            $table->string('address')->nullable();
            $table->string('website')->nullable();
            $table->string('invitation_code')->unique()->nullable();
            // 1 = actif, 0 = désactivé par le super admin
            $table->tinyInteger('status')->default(1);
            $table->foreignUuid('discipline_id')->nullable()->constrained('disciplines')->onDelete('set null');
            $table->foreignUuid('league_id')->nullable()->constrained('leagues')->nullOnDelete();
            $table->foreignUuid('country_id')->nullable()->constrained('countries')->nullOnDelete();

            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('clubs');
    }
};
