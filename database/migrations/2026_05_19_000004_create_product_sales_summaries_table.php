<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_sales_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cafe_id');
            $table->date('date');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name');
            $table->integer('quantity_sold')->default(0);
            $table->integer('total_revenue')->default(0);
            $table->timestamps();

            // Composite unique — her cafe + gün + ürün için tek satır
            $table->unique(['cafe_id', 'date', 'product_id'], 'pss_cafe_date_product_unique');
            $table->index(['cafe_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sales_summaries');
    }
};
