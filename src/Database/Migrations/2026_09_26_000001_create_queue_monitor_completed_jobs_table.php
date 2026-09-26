<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $table = 'queue_monitor_completed_jobs';

    public function up(): void
    {
        if (Schema::hasTable($this->table)) {
            return;
        }

        Schema::create($this->table, function (Blueprint $table) {
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
            $table->index(['job', 'finished_at'], 'queue_monitor_completed_job_finished_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table);
    }
};
