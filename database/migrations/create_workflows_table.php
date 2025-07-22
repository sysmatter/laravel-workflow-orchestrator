<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->uuid('workflow_id')->unique();
            $table->uuid('correlation_id')->index();
            $table->string('type');
            $table->string('status');
            $table->json('context')->nullable();
            $table->string('triggered_by')->nullable()->index(); // e.g., 'user:123', 'system', 'api:webhook'
            $table->string('trigger_type')->nullable(); // e.g., 'manual', 'scheduled', 'webhook', 'event'
            $table->integer('current_action_index')->default(0);
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};
