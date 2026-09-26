<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $table = 'queue_monitor_completed_jobs';

    public function up(): void
    {
        if (! Schema::hasTable($this->table) || Schema::hasColumn($this->table, 'payload')) {
            return;
        }

        Schema::table($this->table, function (Blueprint $table) {
            // longText, not text: a serialized command can exceed the 64 KB text
            // limit. This matches how Laravel stores it in failed_jobs.
            $table->longText('payload')->nullable()->after('uuid');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable($this->table) || ! Schema::hasColumn($this->table, 'payload')) {
            return;
        }

        Schema::table($this->table, function (Blueprint $table) {
            $table->dropColumn('payload');
        });
    }
};
