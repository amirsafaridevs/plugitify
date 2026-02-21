<?php

namespace Plugifity\Migration;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractMigration;
use Plugifity\Core\DB;

/**
 * Migration: Create table for API request logs (url, title, description, from_source, details).
 * Column from_source used to avoid MySQL reserved word (from).
 */
class CreateApiRequestsTable extends AbstractMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::schema()->create($this->getTableName('api_requests'), function ($table) {
            $table->id();
            $table->foreignId('chat_id')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('title', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('from_source', 255)->nullable();
            $table->longText('details')->nullable();
            $table->timestamps();
            $table->index('chat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::schema()->dropIfExists($this->getTableName('api_requests'));
    }
}
