<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_monitor_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('connection')->index();
            $table->string('queue')->index();
            $table->string('period')->index();
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->float('avg_runtime', precision: 5)->nullable();
            $table->float('max_runtime', precision: 5)->nullable();
            $table->timestamps();

            $table->unique(['connection', 'queue', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_monitor_metrics');
    }
};
