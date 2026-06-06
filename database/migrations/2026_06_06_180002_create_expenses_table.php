<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cafe_id');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('category');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->boolean('is_recurring')->default(false);
            $table->unsignedSmallInteger('recurring_day')->nullable();
            $table->string('payment_method')->default('Nakit');
            $table->boolean('is_paid')->default(true);
            $table->unsignedBigInteger('added_by')->nullable();
            $table->timestamps();

            $table->index('cafe_id');
            $table->index('supplier_id');
            $table->index('expense_date');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
