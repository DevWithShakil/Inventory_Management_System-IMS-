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
    Schema::table('purchases', function (Blueprint $table) {
        $table->decimal('paid_amount', 15, 2)->default(0)->after('grand_total');
        $table->decimal('due_amount', 15, 2)->default(0)->after('paid_amount');
        $table->string('payment_status')->default('due')->after('due_amount');
    });
}

public function down()
{
    Schema::table('purchases', function (Blueprint $table) {
        $table->dropColumn(['paid_amount', 'due_amount', 'payment_status']);
    });
}
};
