<?php

namespace Database\Factories;

use App\Models\Import;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Import>
 */
class ImportFactory extends Factory
{
    protected $model = Import::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'format' => 'txt',
            'status' => 'pending',
            'original_filename' => 'import.txt',
        ];
    }
}
