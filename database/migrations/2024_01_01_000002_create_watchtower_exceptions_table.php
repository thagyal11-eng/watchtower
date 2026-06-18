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
        return config('watchtower.table_prefix', 'watchtower_').'exceptions';
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create($this->table(), function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('fingerprint', 64)->unique();
            $table->string('class');
            $table->text('message')->nullable();
            $table->string('file')->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->longText('trace')->nullable();
            $table->string('context_type', 16)->default('other'); // request|job|schedule|other
            $table->unsignedBigInteger('count')->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->index('last_seen_at');
            $table->index('resolved_at');
            $table->index('class');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists($this->table());
    }
};
