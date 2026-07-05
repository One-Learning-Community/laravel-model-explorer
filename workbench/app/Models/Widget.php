<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Factories\WidgetLegacyFactory;

/**
 * Points at a non-conventionally-named factory via the `$factory` property, so
 * `Widget::factory()` returns WidgetLegacyFactory — which the convention guess
 * (WidgetFactory) would never produce. Regression fixture for factory detection.
 */
class Widget extends Model
{
    use HasFactory;

    protected static string $factory = WidgetLegacyFactory::class;
}
