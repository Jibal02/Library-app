<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookStockResyncTest extends TestCase
{
    use RefreshDatabase;

    protected function makeMember(): Member
    {
        $user = User::factory()->create(['role' => 'member']);

        return Member::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }

    public function test_book_factory_defaults_available_to_total(): void
    {
        $book = Book::factory()->create();

        $this->assertSame($book->total_copies, $book->available_copies);
    }

    public function test_resync_fixes_inconsistent_stock(): void
    {
        $member = $this->makeMember();
        $book = Book::factory()->create(['total_copies' => 4, 'available_copies' => 1]);

        Loan::factory()->create([
            'member_id' => $member->id,
            'book_id' => $book->id,
            'status' => 'active',
            'returned_at' => null,
        ]);

        $book->update(['available_copies' => 1]);

        $fixed = Book::resyncStock();

        $this->assertSame(3, $book->fresh()->available_copies);
        $this->assertGreaterThanOrEqual(1, $fixed);
    }

    public function test_resync_never_goes_below_zero(): void
    {
        $member = $this->makeMember();
        $book = Book::factory()->create(['total_copies' => 1, 'available_copies' => 0]);

        Loan::factory()->count(2)->create([
            'member_id' => $member->id,
            'book_id' => $book->id,
            'status' => 'overdue',
            'returned_at' => null,
        ]);

        Book::resyncStock();

        $this->assertSame(0, $book->fresh()->available_copies);
    }

    public function test_resync_ignores_returned_loans(): void
    {
        $member = $this->makeMember();
        $book = Book::factory()->create(['total_copies' => 4, 'available_copies' => 1]);

        Loan::factory()->create([
            'member_id' => $member->id,
            'book_id' => $book->id,
            'status' => 'returned',
            'returned_at' => now()->subDay(),
        ]);

        Book::resyncStock();

        $this->assertSame(4, $book->fresh()->available_copies);
    }

    public function test_resync_command_succeeds(): void
    {
        Book::factory()->create(['total_copies' => 3, 'available_copies' => 0]);

        $this->artisan('books:resync-stock')->assertExitCode(0);
    }
}
