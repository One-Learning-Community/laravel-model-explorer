<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * A model that lives on a non-default database connection. Used to verify that
 * RecordsController::withinSafeRead() opens its rolled-back transaction on the
 * model's own connection rather than the default one.
 *
 * The `audited` accessor performs a write on the secondary connection so a test
 * can assert that write is rolled back after a safe read.
 */
class SecondaryConnectionRecord extends Model
{
    protected $connection = 'secondary';

    protected $table = 'secondary_records';

    public $timestamps = false;

    protected $guarded = [];

    public function getAuditedAttribute(): string
    {
        DB::connection('secondary')->table('write_audits')->insert(['note' => 'accessed']);

        return 'audited';
    }
}
