<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('title');
            $table->text('body');
            $table->string('secret_key')->nullable();
            $table->boolean('is_published')->default(false);
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            // A plain, non-unique index — exercises the `indexed` column flag.
            $table->index('published_at');
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->nullable();
            $table->timestamps();
        });

        // Many-to-many with an extra pivot column — exercises pivot detail.
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('tag_video', function (Blueprint $table) {
            $table->foreignId('tag_id');
            $table->foreignId('video_id');
            $table->integer('sort_order')->default(0);
        });

        // Polymorphic target — exercises the morph type column.
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->morphs('commentable');
            $table->text('body');
            $table->timestamps();
        });

        // Origin of a has-many-through (Country → User → Post).
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('custom_table', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('no_timestamps_models', function (Blueprint $table) {
            $table->id();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
        Schema::dropIfExists('users');
        Schema::dropIfExists('custom_table');
        Schema::dropIfExists('no_timestamps_models');
        Schema::dropIfExists('videos');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('tag_video');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('countries');
    }
};
