<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCoordinatesToRoutesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->decimal('start_latitude', 10, 7)->nullable()->after('start_location');
            $table->decimal('start_longitude', 10, 7)->nullable()->after('start_latitude');
            $table->decimal('end_latitude', 10, 7)->nullable()->after('end_location');
            $table->decimal('end_longitude', 10, 7)->nullable()->after('end_latitude');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->dropColumn('start_latitude');
            $table->dropColumn('start_longitude');
            $table->dropColumn('end_latitude');
            $table->dropColumn('end_longitude');
        });
    }
}
