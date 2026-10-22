<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Read from config rather than hardcoded, so a configured table name is
     * the one the migration creates. The two had to agree.
     */
    protected function table(): string
    {
        return (string) config('filament-queue-monitor.metrics.table', 'queue_monitor_metrics');
    }

    public function up(): void
    {
        $tableName = $this->table();

        Schema::create($tableName, function (Blueprint $table) use ($tableName) {
            $table->id();
            // Sized so the later connection/queue/job/period unique index fits
            // MySQL's 3072 byte key limit under utf8mb4; four 255 character
            // columns need 4080. A period is "Y-m-d H:i:s", 19 characters.
            $table->string('connection', 100)->index();
            $table->string('queue', 191)->index();
            $table->string('period', 32)->index();
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('failed')->default(0);
            // double rather than float(precision: ...): the named argument
            // only exists from Laravel 11, and on Laravel 10 the migration
            // failed with "Unknown named parameter $precision".
            $table->double('avg_runtime')->nullable();
            $table->double('max_runtime')->nullable();
            $table->timestamps();

            // Derived from the table name: index names are global per database,
            // so a fixed name collides once the table is renamed.
            $table->unique(
                ['connection', 'queue', 'period'],
                strlen($tableName.'_connection_queue_period_unique') > 60
                    ? substr($tableName, 0, 60 - strlen('connection_queue_period_unique') - 1).'_connection_queue_period_unique'
                    : $tableName.'_connection_queue_period_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }
};
