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
        Schema::table('past_orders', function (Blueprint $table) {
            $table->string('opened_by_name')->nullable()->after('self_treat');
            // 'closed_by' already exists, we will also add 'closed_by_name' for clarity
            $table->string('closed_by_name')->nullable()->after('closed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('past_orders', function (Blueprint $table) {
            $table->dropColumn(['opened_by_name', 'closed_by_name']);
        });
    }
};
