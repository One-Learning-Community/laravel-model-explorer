<?php

namespace Workbench\App\Vendor;

use Illuminate\Database\Eloquent\Model;

/**
 * Stands in for a third-party/vendor model: a real Eloquent model that lives
 * outside the configured `model_paths`, so ModelDiscovery never finds it.
 * Used to exercise the `mcp.allow_undiscovered` escape hatch.
 */
class PretendVendorModel extends Model
{
    //
}
