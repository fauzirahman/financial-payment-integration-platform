<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('key', 100)->unique();

            $table->string('request_hash', 64);

            $table->string('resource_type', 100);

            $table->uuid('resource_id')->nullable();

            $table->unsignedSmallInteger('response_status')->nullable();

            $table->json('response_body')->nullable();

            $table->string('status', 20)->default('PROCESSING');

            $table->timestamps();

            $table->index([
                'resource_type',
                'resource_id',
            ]);

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};