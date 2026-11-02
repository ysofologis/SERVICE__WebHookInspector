<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_event_id')->constrained('webhook_events')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt'); // 1-based, per event
            $table->unsignedInteger('status_code')->nullable(); // null = transport error
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error')->nullable();
            $table->boolean('delivered')->default(false); // true = 2xx
            $table->timestamps();

            $table->index(['webhook_event_id', 'attempt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
