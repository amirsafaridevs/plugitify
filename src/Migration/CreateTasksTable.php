<?php

namespace Plugifity\Migration;

use Plugifity\Contract\Abstract\AbstractMigration;
use Plugifity\Core\DB;

/**
 * Create Tasks Table
 *
 * Tasks (e.g. background jobs, agent tasks).
 */
class CreateTasksTable extends AbstractMigration
{
    public function up(): void
    {
        DB::schema()->create('plugifity_tasks', function ($table) {
            $table->id();
            $table->foreignId('chat_id')->nullable();
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->string('status', 50)->default('pending');
            $table->timestamps();
            $table->softDeletes();
            $table->index('chat_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        DB::schema()->dropIfExists('plugifity_tasks');
    }
}
