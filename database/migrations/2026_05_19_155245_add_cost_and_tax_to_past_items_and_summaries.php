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
        Schema::table('past_items', function (Blueprint $table) {
            $table->decimal('cost', 10, 2)->default(0)->after('price');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('cost');
        });

        Schema::table('daily_sales_summaries', function (Blueprint $table) {
            $table->integer('total_cost')->default(0)->after('total_turnover');
        });
    }

    public function down(): void
    {
        Schema::table('past_items', function (Blueprint $table) {
            $table->dropColumn(['cost', 'tax_rate']);
        });

        Schema::table('daily_sales_summaries', function (Blueprint $table) {
            $table->dropColumn('total_cost');
        });
    }
};
