<?php

namespace Workbench\App\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\Widget;

/**
 * Deliberately NOT named WidgetFactory: convention resolution would guess
 * WidgetFactory, so this proves detection follows Model::factory() (which honors
 * the model's $factory pointer), not the convention guess.
 */
class WidgetLegacyFactory extends Factory
{
    protected $model = Widget::class;

    public function definition(): array
    {
        return [];
    }
}
