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
    Schema::create('settings', function (Blueprint $table) {
        $table->id();
        $table->string('company_name')->default('My POS Shop');
        $table->string('company_email')->nullable();
        $table->string('company_phone')->nullable();
        $table->text('company_address')->nullable();

        // Branding
        $table->string('logo')->nullable();
        $table->string('currency_symbol')->default('৳');

        // Invoice Settings
        $table->text('invoice_footer_text')->nullable()->default('Thank you for shopping with us!');

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
