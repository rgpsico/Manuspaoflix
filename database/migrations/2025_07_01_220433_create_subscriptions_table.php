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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->constrained()->onDelete('restrict');
            $table->foreignId('address_id')->constrained()->onDelete('restrict');
            $table->string('asaas_subscription_id')->nullable();
            $table->enum('status', ['active', 'paused', 'cancelled', 'pending_payment', 'expired'])->default('active');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_delivery_date')->nullable();
            $table->decimal('price', 8, 2);
            $table->json('preferences')->nullable(); // preferências específicas do cliente
            $table->text('special_instructions')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['status', 'next_delivery_date']);
            $table->index('asaas_subscription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
