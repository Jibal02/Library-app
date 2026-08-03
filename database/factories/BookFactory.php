<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    protected $model = Book::class;

    public function definition(): array
    {
        $total = fake()->numberBetween(1, 10);

        return [
            'category_id' => Category::factory(),
            'isbn' => fake()->unique()->isbn13(),
            'title' => fake()->sentence(4),
            'author' => fake()->name(),
            'publisher' => fake()->company(),
            'publication_year' => fake()->numberBetween(1980, (int) date('Y')),
            'total_copies' => $total,
            'available_copies' => fake()->numberBetween(0, $total),
        ];
    }
}
