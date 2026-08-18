<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSmCourseCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sm_course_categories', function (Blueprint $table) {
            $table->increments('id');
            $table->string('category_name')->nullable();
            $table->text('category_image')->nullable();
            $table->unsignedBigInteger('school_id')->default(1)->unsigned();
            $table->timestamps();
        });
		//----new code to fix course catagory---------
		DB::table('sm_course_categories')->insert([
            [
                'category_name' => 'eSkoolyPro',
                'category_image' => 'public/uploads/settings/academic1.jpg'
            ]
        ]);
		//-----end here---------
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sm_course_categories');
    }
}
