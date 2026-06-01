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
        Schema::create('wastages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cafe_id')->index();
            $table->unsignedBigInteger('material_id')->nullable()->index();
            $table->string('material_name');
            $table->decimal('amount', 10, 2);
            $table->string('unit_type')->nullable(); // weight, volume, piece
            $table->text('description')->nullable();
            $table->decimal('cost', 10, 2)->default(0); // TL cost of the wasted material
            $table->date('date')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wastages');
    }
};
