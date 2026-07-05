<?php

namespace Workbench\App\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\Post;

/**
 * Fixture proving factory detection. Lives under the autoloaded Workbench\App
 * namespace; the factory tests point the name resolver here.
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [];
    }
}
