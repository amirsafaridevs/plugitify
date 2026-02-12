<?php

namespace Plugifity\Migration;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractMigration;
use Plugifity\Core\DB;

/**
 * Migration: Create table for API request logs (url, title, description, from, details).
 */
class CreateApiRequestsTable extends AbstractMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::schema()->create('plugifity_api_requests', function ($table) {
            $table->id();
            $table->string('url', 2048)->nullable();
            $table->string('title', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('from', 255)->nullable();
            $table->longText('details')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::schema()->dropIfExists('plugifity_api_requests');
    }
}
