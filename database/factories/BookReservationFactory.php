<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\BookReservation;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookReservation>
 */
class BookReservationFactory extends Factory
{
    protected $model = BookReservation::class;

    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'book_id' => Book::factory(),
            'reserved_at' => fake()->dateTimeBetween('-2 weeks', 'now'),
            'status' => 'pending',
        ];
    }
}
