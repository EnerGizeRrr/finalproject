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
        Schema::create('webhook_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('webhook_subscription_id')->constrained()->onDelete('cascade');
            $table->string('status'); // 'success' or 'failed'
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->text('response_body')->nullable();
            $table->json('payload');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_attempts');
    }
};
