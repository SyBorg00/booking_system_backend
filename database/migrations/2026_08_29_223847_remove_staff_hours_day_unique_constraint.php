
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
        Schema::table('staff_hours', function (Blueprint $table) {
            // Temporarily remove the foreign key
            // that depends on the current index.
            $table->dropForeign('staff_hours_staff_id_foreign');

            // Remove the UNIQUE(staff_id, day_of_week) constraint.
            $table->dropUnique('staff_hours_staff_id_day_of_week_unique');

            // Replace it with a normal index.
            $table->index(
                ['staff_id', 'day_of_week'],
                'staff_hours_staff_id_day_of_week_index'
            );

            // Re-create the foreign key.
            $table->foreign('staff_id')
                ->references('id')
                ->on('staff')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_hours', function (Blueprint $table) {
            // Remove the foreign key.
            $table->dropForeign('staff_hours_staff_id_foreign');

            // Remove the normal index.
            $table->dropIndex('staff_hours_staff_id_day_of_week_index');

            // Restore the original unique constraint.
            $table->unique(
                ['staff_id', 'day_of_week'],
                'staff_hours_staff_id_day_of_week_unique'
            );

            // Restore the foreign key.
            $table->foreign('staff_id')
                ->references('id')
                ->on('staff')
                ->cascadeOnDelete();
        });
    }
};
