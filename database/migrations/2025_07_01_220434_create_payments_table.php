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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('asaas_payment_id')->nullable();
            $table->string('asaas_invoice_url')->nullable();
            $table->decimal('amount', 8, 2);
            $table->enum('status', ['pending', 'confirmed', 'received', 'overdue', 'refunded', 'cancelled'])->default('pending');
            $table->enum('billing_type', ['BOLETO', 'CREDIT_CARD', 'PIX', 'DEBIT_CARD'])->default('BOLETO');
            $table->date('due_date');
            $table->date('payment_date')->nullable();
            $table->text('description')->nullable();
            $table->json('asaas_response')->nullable(); // resposta completa da API do Asaas
            $table->text('failure_reason')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('last_retry_at')->nullable();
            $table->timestamps();
            
            $table->index(['subscription_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'due_date']);
            $table->index('asaas_payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
