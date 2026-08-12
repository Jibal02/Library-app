<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Category;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $categories = Category::factory()->count(8)->create();

        $books = Book::factory()->count(25)->create([
            'category_id' => fn () => $categories->random()->id,
        ]);

        $members = Member::factory()->count(10)->create();

        Loan::factory()->count(20)->create([
            'member_id' => fn () => $members->random()->id,
            'book_id' => fn () => $books->random()->id,
        ]);

        Book::resyncStock();
    }
}
