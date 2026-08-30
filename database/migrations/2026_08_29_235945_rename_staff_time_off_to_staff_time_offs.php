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
        //this should have been done during creation, but let's leave it at that
        Schema::rename('staff_time_off', 'staff_time_offs');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_time_offs', function (Blueprint $table) {
            Schema::rename('staff_time_offs', 'staff_time_off');
        });
    }
};
