<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    protected $model = Loan::class;

    private const FINE_PER_DAY = 2000;

    public function definition(): array
    {
        $borrowedAt = fake()->dateTimeBetween('-3 months', 'now');
        $dueDate = (clone $borrowedAt)->modify('+14 days');

        if (fake()->boolean(70)) {
            $returnedAt = fake()->dateTimeBetween($borrowedAt, 'now');
            $lateDays = max(0, (int) $dueDate->diff($returnedAt)->format('%r%a'));

            return [
                'member_id' => Member::factory(),
                'book_id' => Book::factory(),
                'borrowed_at' => $borrowedAt,
                'due_date' => $dueDate,
                'returned_at' => $returnedAt,
                'fine_amount' => $lateDays > 0 ? $lateDays * self::FINE_PER_DAY : 0,
                'status' => 'returned',
            ];
        }

        return [
            'member_id' => Member::factory(),
            'book_id' => Book::factory(),
            'borrowed_at' => $borrowedAt,
            'due_date' => $dueDate,
            'returned_at' => null,
            'fine_amount' => 0,
            'status' => $dueDate < now() ? 'overdue' : 'active',
        ];
    }
}
