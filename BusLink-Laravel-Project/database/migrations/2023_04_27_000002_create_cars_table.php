<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->string('plate_number')->unique();
            $table->string('model');
            $table->integer('capacity');
            $table->integer('year');
            $table->string('status')->default('active'); // active, maintenance, blocked
            $table->timestamps();
        });

        // Add foreign key constraint to driver_profiles table
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->foreign('car_id')->references('id')->on('cars')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->dropForeign(['car_id']);
        });
        
        Schema::dropIfExists('cars');
    }
};
