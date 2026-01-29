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
    Schema::create('sales_returns', function (Blueprint $table) {
        $table->id();
        $table->foreignId('sale_id')->constrained('sales')->onDelete('cascade');
        $table->foreignId('customer_id')->nullable()->constrained('customers');
        $table->string('return_no')->unique();
        $table->date('date');
        $table->decimal('total_amount', 15, 2);
        $table->decimal('deduction_amount', 15, 2)->default(0);
        $table->decimal('refund_amount', 15, 2);
        $table->text('note')->nullable();
        $table->foreignId('created_by')->constrained('users');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_returns');
    }
};
