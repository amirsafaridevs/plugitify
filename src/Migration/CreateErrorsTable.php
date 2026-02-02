<?php

namespace Plugifity\Migration;

use Plugifity\Contract\Abstract\AbstractMigration;
use Plugifity\Core\DB;

/**
 * Create Errors Table
 *
 * Error log / exception storage.
 */
class CreateErrorsTable extends AbstractMigration
{
    public function up(): void
    {
        DB::schema()->create('plugifity_errors', function ($table) {
            $table->id();
            $table->text('message');
            $table->longText('context')->nullable();
            $table->string('code', 100)->nullable();
            $table->string('level', 50)->nullable();
            $table->string('file', 500)->nullable();
            $table->integer('line')->nullable();
            $table->timestamps();
            $table->index('code');
            $table->index('level');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        DB::schema()->dropIfExists('plugifity_errors');
    }
}
