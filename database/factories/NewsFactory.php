<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class NewsFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(6);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 999999),
            'excerpt' => fake()->sentence(15),
            'body' => '<p>'.fake()->paragraphs(3, true).'</p>',
            'category' => fake()->randomElement(['HR Tips', 'Regulasi', 'Produk']),
            'image_path' => null,
            'status' => 'published',
            'is_featured' => false,
            'published_at' => now(),
        ];
    }
}
