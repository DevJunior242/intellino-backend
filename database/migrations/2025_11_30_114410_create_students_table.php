
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
        Schema::create('students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users');
            $table->boolean('is_adult')->default(false);
            $table->string('fullname');
            $table->date('birthdate');
            $table->string('matricule')->nullable();
            $table->enum('sex', ['M', 'F']);
            $table->string('photo')->nullable();
            //subscription_expires_at
            $table->timestamp('subscription_expires_at')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
