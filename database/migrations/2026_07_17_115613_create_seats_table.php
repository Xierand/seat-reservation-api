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
        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained()->cascadeOnDelete();
            $table->string('row', 50)->nullable(); //only fore sector->type['seated'/'mixed']
            $table->string('number', 50)->nullable(); // - || -
            $table->string('status')->default('free');
            $table->decimal('base_price', 10, 2);
            $table->timestamps();

            // faster search by sector_id and status
            $table->index(['sector_id', 'status'], 'idx_sector_status');
            $table->unique(['sector_id', 'row', 'number'], 'uq_sector_row_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};
