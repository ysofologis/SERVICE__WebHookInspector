<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('method', 16);
            $table->string('path', 2048)->default('/'); // request target incl. query string
            $table->json('query')->nullable();
            $table->json('headers');
            $table->longText('body')->nullable();
            $table->string('content_type', 191)->nullable();
            $table->unsignedBigInteger('payload_size')->default(0);
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['endpoint_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
