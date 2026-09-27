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
            $table->string('connection')->index();
            $table->string('queue')->index();
            $table->string('period')->index();
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->float('avg_runtime', precision: 5)->nullable();
            $table->float('max_runtime', precision: 5)->nullable();
            $table->timestamps();

            // Derived from the table name: index names are global per database,
            // so a fixed name collides once the table is renamed.
            $table->unique(
                ['connection', 'queue', 'period'],
                strlen($tableName.'_connection_queue_period_unique') > 60
                    ? substr($tableName, 0, 30).'_connection_queue_period_unique'
                    : $tableName.'_connection_queue_period_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }
};
