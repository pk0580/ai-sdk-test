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
        Schema::create('ai_logs', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->index()->nullable();
            $table->string('agent_name')->index();
            $table->text('thought')->nullable();
            $table->string('action')->nullable();
            $table->json('input')->nullable();
            $table->text('output')->nullable();
            $table->float('latency')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_logs');
    }
};
