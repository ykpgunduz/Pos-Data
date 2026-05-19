<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('past_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cafe_id');
            $table->string('order_number');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name')->nullable();
            $table->integer('quantity')->default(1);
            $table->integer('price')->default(0);
            $table->timestamps();

            $table->index('order_number');
            $table->index(['cafe_id', 'order_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('past_items');
    }
};
