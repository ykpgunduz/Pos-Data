<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cari_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cafe_id');
            $table->string('name');
            $table->string('customer_type')->default('Bireysel');
            $table->string('company_name')->nullable();
            $table->string('tax_number', 50)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('gender')->nullable();
            $table->date('birthday')->nullable();
            $table->text('address')->nullable();
            $table->decimal('credit_limit', 10, 2)->default(0);
            $table->decimal('current_balance', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('cafe_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cari_accounts');
    }
};
