<?php

namespace Plugifity\Migration;

use Plugifity\Contract\Abstract\AbstractMigration;
use Plugifity\Core\DB;

/**
 * Create Chats Table
 *
 * Chat sessions (e.g. for AI/conversation features).
 */
class CreateChatsTable extends AbstractMigration
{
    public function up(): void
    {
        DB::schema()->create('plugifity_chats', function ($table) {
            $table->id();
            $table->string('title', 500)->nullable();
            $table->string('status', 50)->default('active');
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        DB::schema()->dropIfExists('plugifity_chats');
    }
}
