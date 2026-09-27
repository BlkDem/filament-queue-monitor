<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected function table(): string
    {
        return (string) config('filament-queue-monitor.metrics.table_completed_jobs', 'queue_monitor_completed_jobs');
    }

    public function up(): void
    {
        if (Schema::hasTable($this->table())) {
            return;
        }

        $tableName = $this->table();

        Schema::create($tableName, function (Blueprint $table) use ($tableName) {
            $table->id();

            // Queue connection the job ran on, so a driver switch does not
            // blend the runs of two configurations.
            $table->string('connection')->index();
            $table->string('queue')->index();
            $table->string('job')->index();

            // Job instance identifier, when the backend exposes one.
            $table->string('uuid')->nullable()->index();

            $table->float('runtime')->nullable();
            $table->timestamp('finished_at')->index();

            $table->timestamps();

            // Listing one class newest first, which is what the drill-down does.
            // Named after the table so a configured table name does not collide
            // with the default one, and kept inside the 64 character limit.
            $table->index(['job', 'finished_at'], $this->indexName($tableName, 'finished_idx'));
        });
    }

    /**
     * Index names are global per database, so a fixed name breaks as soon as
     * the table is renamed.
     */
    protected function indexName(string $table, string $suffix): string
    {
        $name = $table.'_'.$suffix;

        return strlen($name) > 60 ? substr($table, 0, 60 - strlen($suffix) - 1).'_'.$suffix : $name;
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }
};
