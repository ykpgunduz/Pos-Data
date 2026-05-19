<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_sales_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cafe_id');
            $table->date('date');
            $table->integer('total_turnover')->default(0);
            $table->integer('total_orders')->default(0);
            $table->integer('total_net_amount')->default(0);
            $table->integer('total_tax_amount')->default(0);
            $table->integer('total_cash')->default(0);
            $table->integer('total_card')->default(0);
            $table->integer('total_iban')->default(0);
            $table->integer('total_treat')->default(0);
            $table->integer('total_discount')->default(0);
            $table->integer('total_customers')->default(0);
            $table->integer('total_customer_male')->default(0);
            $table->integer('total_customer_female')->default(0);
            $table->integer('total_customer_child')->default(0);
            $table->timestamps();

            // Composite unique index — her cafe için günde tek satır
            $table->unique(['cafe_id', 'date']);
            $table->index(['cafe_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_sales_summaries');
    }
};
