<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->decimal('price', 8, 2);
            $table->enum('frequency', ['daily', 'alternate_days', 'weekends', 'weekly', 'monthly']);
            $table->json('delivery_days')->nullable(); // [1,2,3,4,5] para seg-sex, [6,0] para fins de semana
            $table->integer('bread_quantity')->default(1);
            $table->json('bread_types')->nullable(); // ['francês', 'integral', 'doce']
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
