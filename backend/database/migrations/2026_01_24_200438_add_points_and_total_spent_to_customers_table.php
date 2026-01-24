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
        if (!Schema::hasColumn('customers', 'points')) {
            $table->integer('points')->default(0)->nullable();
        }
        if (!Schema::hasColumn('customers', 'total_spent')) {
            $table->decimal('total_spent', 10, 2)->default(0)->nullable();
        }
    });
}

public function down(): void
{
    Schema::table('customers', function (Blueprint $table) {
        $table->dropColumn(['points', 'total_spent']);
    });
}
};
