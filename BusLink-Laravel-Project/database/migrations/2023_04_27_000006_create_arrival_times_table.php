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
        Schema::create('arrival_times', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->onDelete('cascade');
            $table->dateTime('check_in_time');
            $table->date('check_in_date');
            $table->string('status'); // on-time, late, absent
            $table->timestamps();

            // Add unique constraint to ensure one check-in per driver per day
            $table->unique(['driver_id', 'check_in_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('arrival_times');
    }
};
