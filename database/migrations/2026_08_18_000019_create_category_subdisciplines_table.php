<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_subdisciplines', function (Blueprint $table) {
            $table->foreignUuid('category_id')->constrained('categories')->onDelete('cascade');
            $table->foreignUuid('sub_discipline_id')->constrained('sub_disciplines')->onDelete('cascade');
            $table->primary(['category_id', 'sub_discipline_id']);
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('category_subdisciplines');
    }
};
