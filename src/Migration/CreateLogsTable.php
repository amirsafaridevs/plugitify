<?php

namespace Plugifity\Migration;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractMigration;
use Plugifity\Core\DB;

/**
 * Migration: Create table for logs (type, message, context, timestamps).
 */
class CreateLogsTable extends AbstractMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::schema()->create('plugifity_logs', function ($table) {
            $table->id();
            $table->string('type', 100);
            $table->text('message')->nullable();
            $table->longText('context')->nullable();
            $table->timestamps();
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::schema()->dropIfExists('plugifity_logs');
    }
}
