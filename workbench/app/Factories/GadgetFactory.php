<?php

namespace Workbench\App\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\Gadget;

/**
 * Conventionally-named factory reached via the name resolver — exercises the
 * vanilla HasFactory path (newFactory() null → factoryForModel()).
 */
class GadgetFactory extends Factory
{
    protected $model = Gadget::class;

    public function definition(): array
    {
        return [];
    }
}
