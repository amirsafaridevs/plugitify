<?php

namespace Plugifity\Migration;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractMigration;
use Plugifity\Core\DB;

/**
 * Migration: Create table for changes (type, from_value, to_value, details).
 * Column names from_value/to_value used to avoid MySQL reserved words (from, to).
 */
class CreateChangesTable extends AbstractMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::schema()->create($this->getTableName('changes'), function ($table) {
            $table->id();
            $table->foreignId('chat_id')->nullable();
            $table->string('type', 100)->nullable();
            $table->longText('from_value')->nullable();
            $table->longText('to_value')->nullable();
            $table->longText('details')->nullable();
            $table->timestamps();
            $table->index('type');
            $table->index('chat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::schema()->dropIfExists($this->getTableName('changes'));
    }
}
