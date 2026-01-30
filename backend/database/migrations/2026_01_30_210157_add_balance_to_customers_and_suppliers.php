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
    Schema::table('customers', function (Blueprint $table) {
        $table->decimal('balance', 15, 2)->default(0)->comment('Positive=Due, Negative=Advance')->after('phone');
    });

    Schema::table('suppliers', function (Blueprint $table) {
        $table->decimal('balance', 15, 2)->default(0)->comment('Positive=We Owe, Negative=Advance')->after('phone');
    });
}

public function down(): void
{
    Schema::table('customers', function (Blueprint $table) {
        $table->dropColumn('balance');
    });
    Schema::table('suppliers', function (Blueprint $table) {
        $table->dropColumn('balance');
    });
}
};
