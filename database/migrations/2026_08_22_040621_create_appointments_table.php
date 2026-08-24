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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            //restrictOnDelete() is used to prevent deletion of a customer or staff if they have appointments (for archival reasons)
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();

            $table->foreignId('staff_id')
                ->constrained('staff')
                ->restrictOnDelete();

            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime');

            $table->enum('status', [
                'pending',
                'confirmed',
                'completed',
                'cancelled',
                'no_show',
            ])->default('pending');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index([
                'business_id',
                'start_datetime',
            ]);

            $table->index([
                'staff_id',
                'start_datetime',
                'end_datetime',
            ]);

            $table->index([
                'customer_id',
                'start_datetime',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
