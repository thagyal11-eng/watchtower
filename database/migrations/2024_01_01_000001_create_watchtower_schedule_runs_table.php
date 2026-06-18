<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('watchtower.connection') ?: parent::getConnection();
    }

    protected function table(): string
    {
        return config('watchtower.table_prefix', 'watchtower_').'schedule_runs';
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create($this->table(), function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('task_key', 64);
            $table->string('command');
            $table->string('expression', 64)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('status', 16)->default('running'); // running|success|failed|skipped
            $table->longText('output')->nullable();
            $table->integer('exit_code')->nullable();
            $table->string('host', 128)->nullable();

            $table->index(['task_key', 'started_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists($this->table());
    }
};
