<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('type', 20);
            $table->string('normal_balance', 10);

            $table->uuid('parent_id')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('type');
            $table->index('is_active');
        });

        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('chart_of_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};