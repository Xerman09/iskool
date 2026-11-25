<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * What a year level costs in tuition, for a basic-education deployment.
     *
     * A college already has a home for this: price_per_unit on the course, with
     * the amount worked out from the subject load. Basic education had none, so
     * tuition was being entered as a miscellaneous fee - the largest charge in
     * the school living in a screen named for the leftovers, and no way for any
     * report to tell tuition from an ID fee, since both were rows in the same
     * table.
     *
     * Keyed by academic year rather than held on sm_classes, so last year's
     * tuition is still on file after this year's is raised. A learner's invoice
     * can then be read back against the price that was in force when it was
     * raised, instead of against today's.
     *
     * Nothing here is seeded. The amounts are the school's to enter, and a zero
     * standing in for "not set yet" would be billed as a free year level.
     */
    public function up(): void
    {
        if (Schema::hasTable('class_tuitions')) {
            return;
        }

        Schema::create('class_tuitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('class_id');
            $table->unsignedInteger('academic_id');
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedInteger('school_id')->default(1);
            $table->timestamps();

            /* One amount per year level per year. The save path relies on this:
               it updates whatever is already there rather than adding beside it,
               and without the constraint a double submit would leave two. */
            $table->unique(['class_id', 'academic_id', 'school_id'], 'class_tuitions_level_year_school');
            $table->index(['school_id', 'academic_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_tuitions');
    }
};
