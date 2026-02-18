<?php

namespace Plugifity\Migration;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractMigration;
use Plugifity\Core\DB;

/**
 * Migration: Create table for chats (title, deleted_at, timestamps).
 */
class CreateChatsTable extends AbstractMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::schema()->create($this->getTableName('chats'), function ($table) {
            $table->id();
            $table->string('title', 500)->nullable();
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::schema()->dropIfExists($this->getTableName('chats'));
    }
}
