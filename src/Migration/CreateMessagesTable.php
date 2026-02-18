<?php

namespace Plugifity\Migration;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractMigration;
use Plugifity\Core\DB;

/**
 * Migration: Create table for messages (chat_id, content, timestamps).
 */
class CreateMessagesTable extends AbstractMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::schema()->create($this->getTableName('messages'), function ($table) {
            $table->id();
            $table->string('role', 191);
            $table->foreignId('chat_id');
            $table->longText('content')->nullable();
            $table->timestamps();
            $table->index(['chat_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::schema()->dropIfExists($this->getTableName('messages'));
    }
}
