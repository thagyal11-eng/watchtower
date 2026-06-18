<?php

namespace Watchtower\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base model: all Watchtower tables honour the configurable connection and
 * table prefix so they can live on a dedicated database.
 */
abstract class WatchtowerModel extends Model
{
    public $timestamps = false;

    /**
     * The un-prefixed table name, set by each subclass.
     */
    protected string $baseTable = '';

    public function getConnectionName(): ?string
    {
        return config('watchtower.connection') ?: parent::getConnectionName();
    }

    public function getTable(): string
    {
        return config('watchtower.table_prefix', 'watchtower_').$this->baseTable;
    }
}
