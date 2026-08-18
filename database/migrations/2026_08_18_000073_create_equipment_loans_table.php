<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_loans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('equipment_id')->constrained('equipment');
            $table->foreignUuid('user_id')->constrained('users');
            $table->foreignUuid('club_id')->constrained('clubs');
            $table->foreignUuid('to_club_id')
                ->nullable()
                ->constrained('clubs')->nullOnDelete();
            $table->integer('quantity_loaned')->default(1);
            $table->integer('quantity_returned')->default(0);
            $table->integer('quantity_lost')->default(0);
            $table->integer('quantity_damaged')->default(0);

            $table->timestamp('loaned_at');
            $table->timestamp('returned_at')->nullable();
            $table->enum('type', ['internal', 'external'])->default('external');
            $table->string('beneficiary')->nullable();
            $table->enum('status', ['active', 'returned', 'lost', 'damaged', 'partial'])->default('active');
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_loans');
    }
};
