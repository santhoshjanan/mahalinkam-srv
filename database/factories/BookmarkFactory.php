<?php

namespace Database\Factories;

use App\Enums\MetadataStatus;
use App\Models\Bookmark;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bookmark>
 */
class BookmarkFactory extends Factory
{
    protected $model = Bookmark::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $url = fake()->unique()->url();

        return [
            'user_id' => User::factory(),
            'folder_id' => null,
            'url' => $url,
            'normalized_url' => rtrim($url, '/'),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->sentence(),
            'favicon_url' => null,
            'metadata_status' => MetadataStatus::Done->value,
        ];
    }
}
