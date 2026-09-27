<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $oldIndex = 'queue_monitor_metrics_connection_queue_period_unique';

    protected string $newIndex = 'queue_monitor_metrics_connection_queue_job_period_unique';

    protected function table(): string
    {
        return (string) config('filament-queue-monitor.metrics.table', 'queue_monitor_metrics');
    }

    /**
     * Index names are global per database, so they are derived from the
     * configured table name and trimmed to stay inside the 64 character limit.
     */
    protected function indexName(string $suffix): string
    {
        $name = $this->table().'_'.$suffix;

        return strlen($name) > 60 ? substr($this->table(), 0, 60 - strlen($suffix) - 1).'_'.$suffix : $name;
    }

    public function up(): void
    {
        if (! Schema::hasTable($this->table())) {
            return;
        }

        if (! Schema::hasColumn($this->table(), 'job')) {
            Schema::table($this->table(), function (Blueprint $table) {
                $table->string('job')->default('unknown')->after('queue');
            });
        }

        $oldIndex = $this->indexName('connection_queue_period_unique');
        $newIndex = $this->indexName('connection_queue_job_period_unique');

        if ($oldIndex !== $this->oldIndex && Schema::hasIndex($this->table(), $this->oldIndex)) {
            $oldIndex = $this->oldIndex;
        }

        if (Schema::hasIndex($this->table(), $oldIndex)) {
            Schema::table($this->table(), function (Blueprint $table) use ($oldIndex) {
                $table->dropUnique($oldIndex);
            });
        }

        DB::table($this->table())->whereNull('job')->orWhere('job', '')->update(['job' => 'unknown']);

        if (! Schema::hasIndex($this->table(), $newIndex)) {
            Schema::table($this->table(), function (Blueprint $table) use ($newIndex) {
                $table->unique(['connection', 'queue', 'job', 'period'], $newIndex);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable($this->table()) || ! Schema::hasColumn($this->table(), 'job')) {
            return;
        }

        $oldIndex = $this->indexName('connection_queue_period_unique');
        $newIndex = $this->indexName('connection_queue_job_period_unique');

        if (Schema::hasIndex($this->table(), $newIndex)) {
            Schema::table($this->table(), function (Blueprint $table) use ($newIndex) {
                $table->dropUnique($newIndex);
            });
        }

        if (! Schema::hasIndex($this->table(), $oldIndex)) {
            Schema::table($this->table(), function (Blueprint $table) use ($oldIndex) {
                $table->unique(['connection', 'queue', 'period'], $oldIndex);
            });
        }

        Schema::table($this->table(), function (Blueprint $table) {
            $table->dropColumn('job');
        });
    }
};