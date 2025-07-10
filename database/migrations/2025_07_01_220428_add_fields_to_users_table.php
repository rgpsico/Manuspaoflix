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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable();
            $table->string('cpf')->unique()->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('role', ['customer', 'admin', 'delivery'])->default('customer');
            $table->string('asaas_customer_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'cpf',
                'birth_date',
                'role',
                'asaas_customer_id',
                'is_active',
                'last_login_at'
            ]);
        });
    }
};
