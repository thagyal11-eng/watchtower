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
        return config('watchtower.table_prefix', 'watchtower_').'job_records';
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create($this->table(), function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('uuid', 64)->nullable()->index();
            $table->string('connection', 64)->nullable();
            $table->string('queue', 128)->nullable();
            $table->string('name'); // job class
            $table->string('status', 16)->default('queued'); // queued|processing|processed|failed|released
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->longText('payload')->nullable();
            $table->unsignedBigInteger('exception_id')->nullable();

            $table->index(['status', 'finished_at']);
            $table->index('queue');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists($this->table());
    }
};
