<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('sales_return_items', function (Blueprint $table) {
        $table->string('return_condition')->default('good')->after('quantity');
    });
}

public function down()
{
    Schema::table('sales_return_items', function (Blueprint $table) {
        $table->dropColumn('return_condition');
    });
}
};
