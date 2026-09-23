<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $table = 'queue_monitor_metrics';

    protected string $oldIndex = 'queue_monitor_metrics_connection_queue_period_unique';

    protected string $newIndex = 'queue_monitor_metrics_connection_queue_job_period_unique';

    public function up(): void
    {
        if (! Schema::hasTable($this->table)) {
            return;
        }

        if (! Schema::hasColumn($this->table, 'job')) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->string('job')->default('unknown')->after('queue');
            });
        }

        if (Schema::hasIndex($this->table, $this->oldIndex)) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->dropUnique($this->oldIndex);
            });
        }

        DB::table($this->table)->whereNull('job')->orWhere('job', '')->update(['job' => 'unknown']);

        if (! Schema::hasIndex($this->table, $this->newIndex)) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->unique(['connection', 'queue', 'job', 'period'], $this->newIndex);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable($this->table) || ! Schema::hasColumn($this->table, 'job')) {
            return;
        }

        if (Schema::hasIndex($this->table, $this->newIndex)) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->dropUnique($this->newIndex);
            });
        }

        if (! Schema::hasIndex($this->table, $this->oldIndex)) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->unique(['connection', 'queue', 'period']);
            });
        }

        Schema::table($this->table, function (Blueprint $table) {
            $table->dropColumn('job');
        });
    }
};