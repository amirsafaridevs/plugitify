<?php

namespace Plugifity\Migration;

use Plugifity\Contract\Abstract\AbstractMigration;
use Plugifity\Core\DB;

/**
 * Create Messages Table
 *
 * Messages belonging to a chat (user/assistant/system).
 */
class CreateMessagesTable extends AbstractMigration
{
    public function up(): void
    {
        DB::schema()->create('plugifity_messages', function ($table) {
            $table->id();
            $table->foreignId('chat_id');
            $table->string('role', 50);
            $table->longText('content')->nullable();
            $table->timestamps();
            $table->index('chat_id');
            $table->index(['chat_id', 'created_at']);
        });
    }

    public function down(): void
    {
        DB::schema()->dropIfExists('plugifity_messages');
    }
}
