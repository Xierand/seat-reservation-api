<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 255);
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->decimal('total_amount', 10, 2);
            $table->dateTime('valid_until');
            $table->string('currency')->default('USD');
            $table->string('payment_provider_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'valid_until'], 'idx_status_valid_until');
            $table->index('user_id', 'idx_user_id');
            $table->index('payment_provider_id', 'idx_payment_provider_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
