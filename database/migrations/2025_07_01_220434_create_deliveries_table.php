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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // cliente
            $table->foreignId('delivery_person_id')->nullable()->constrained('users')->onDelete('set null'); // entregador
            $table->date('scheduled_date');
            $table->time('scheduled_time_start')->nullable();
            $table->time('scheduled_time_end')->nullable();
            $table->enum('status', ['scheduled', 'in_route', 'delivered', 'failed', 'cancelled'])->default('scheduled');
            $table->timestamp('delivered_at')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->text('customer_feedback')->nullable();
            $table->integer('rating')->nullable(); // 1-5 estrelas
            $table->json('items_delivered')->nullable(); // detalhes dos itens entregues
            $table->string('delivery_photo')->nullable(); // foto da entrega
            $table->decimal('delivery_latitude', 10, 8)->nullable();
            $table->decimal('delivery_longitude', 11, 8)->nullable();
            $table->timestamps();
            
            $table->index(['subscription_id', 'scheduled_date']);
            $table->index(['delivery_person_id', 'scheduled_date']);
            $table->index(['status', 'scheduled_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
