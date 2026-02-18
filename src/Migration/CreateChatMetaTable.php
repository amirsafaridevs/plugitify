<?php

namespace Plugifity\Migration;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractMigration;
use Plugifity\Core\DB;

/**
 * Migration: Create table for chat meta (chat_id, meta_key, meta_value).
 */
class CreateChatMetaTable extends AbstractMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::schema()->create($this->getTableName('chat_meta'), function ($table) {
            $table->id();
            $table->foreignId('chat_id');
            $table->string('meta_key', 255)->nullable();
            $table->longText('meta_value')->nullable();
            $table->index('chat_id');
            $table->index('meta_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::schema()->dropIfExists($this->getTableName('chat_meta'));
    }
}
