<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('past_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cafe_id');
            $table->string('order_number')->index();
            $table->integer('table_number')->nullable();
            $table->unsignedBigInteger('cari_account_id')->nullable();
            $table->integer('customer')->nullable();
            $table->unsignedSmallInteger('customer_male')->default(0);
            $table->unsignedSmallInteger('customer_female')->default(0);
            $table->unsignedSmallInteger('customer_child')->default(0);
            $table->integer('total_amount')->default(0);
            $table->integer('net_amount')->default(0);
            $table->integer('cash')->nullable();
            $table->integer('card')->nullable();
            $table->string('iban')->nullable();
            $table->integer('treat')->nullable();
            $table->integer('discount')->nullable();
            $table->string('self_treat')->nullable();
            $table->string('closed_by')->nullable();
            $table->timestamps();

            $table->index(['cafe_id', 'created_at']);
            $table->unique(['cafe_id', 'order_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('past_orders');
    }
};
