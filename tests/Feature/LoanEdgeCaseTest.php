<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoanEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    protected function makeMember(array $userAttributes = [], array $cardAttributes = []): User
    {
        $user = User::factory()->create(array_merge(['role' => 'member'], $userAttributes));

        Member::factory()->create(array_merge([
            'user_id' => $user->id,
            'status' => 'active',
        ], $cardAttributes));

        return $user;
    }

    protected function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_suspended_member_cannot_issue(): void
    {
        $member = $this->makeMember([], ['status' => 'suspended']);
        $book = Book::factory()->create(['total_copies' => 5, 'available_copies' => 5]);

        Sanctum::actingAs($this->makeAdmin());

        $this->postJson('/api/v1/loans/issue', [
            'member_id' => $member->member->id,
            'book_id' => $book->id,
        ])
            ->assertStatus(422)
            ->assertJson(['message' => 'Member tidak aktif (suspended).']);
    }

    public function test_out_of_stock_book_cannot_be_issued(): void
    {
        $member = $this->makeMember();
        $book = Book::factory()->create(['total_copies' => 1, 'available_copies' => 0]);

        Sanctum::actingAs($this->makeAdmin());

        $this->postJson('/api/v1/loans/issue', [
            'member_id' => $member->member->id,
            'book_id' => $book->id,
        ])
            ->assertStatus(422)
            ->assertJson(['message' => 'Buku sedang habis / tidak tersedia.']);
    }

    public function test_member_cannot_borrow_same_book_twice(): void
    {
        $member = $this->makeMember();
        $book = Book::factory()->create(['total_copies' => 5, 'available_copies' => 5]);

        Loan::factory()->create([
            'member_id' => $member->member->id,
            'book_id' => $book->id,
            'borrowed_at' => now()->subDays(1),
            'due_date' => now()->addDays(13),
            'returned_at' => null,
            'status' => 'active',
            'fine_amount' => 0,
        ]);

        Sanctum::actingAs($this->makeAdmin());

        $this->postJson('/api/v1/loans/issue', [
            'member_id' => $member->member->id,
            'book_id' => $book->id,
        ])
            ->assertStatus(422)
            ->assertJson(['message' => 'Member masih meminjam buku ini.']);
    }

    public function test_double_return_rejected(): void
    {
        $member = $this->makeMember();
        $book = Book::factory()->create(['total_copies' => 5, 'available_copies' => 5]);

        $loan = Loan::factory()->create([
            'member_id' => $member->member->id,
            'book_id' => $book->id,
            'borrowed_at' => now()->subDays(20),
            'due_date' => now()->subDays(6),
            'returned_at' => now()->subDays(1),
            'status' => 'returned',
            'fine_amount' => 12000,
        ]);

        Sanctum::actingAs($this->makeAdmin());

        $this->postJson("/api/v1/loans/{$loan->id}/return")
            ->assertStatus(422)
            ->assertJson(['message' => 'Buku ini sudah dikembalikan sebelumnya.']);
    }

    public function test_issue_decrements_stock(): void
    {
        $member = $this->makeMember();
        $book = Book::factory()->create(['total_copies' => 5, 'available_copies' => 5]);

        Sanctum::actingAs($this->makeAdmin());

        $this->postJson('/api/v1/loans/issue', [
            'member_id' => $member->member->id,
            'book_id' => $book->id,
        ])
            ->assertStatus(201);

        $this->assertEquals(4, $book->fresh()->available_copies);
    }

    public function test_return_increments_stock_and_calculates_fine(): void
    {
        $member = $this->makeMember();
        $book = Book::factory()->create(['total_copies' => 3, 'available_copies' => 1]);

        $loan = Loan::factory()->create([
            'member_id' => $member->member->id,
            'book_id' => $book->id,
            'borrowed_at' => now()->subDays(20),
            'due_date' => now()->subDays(6),
            'returned_at' => null,
            'status' => 'active',
            'fine_amount' => 0,
        ]);

        Sanctum::actingAs($this->makeAdmin());

        $this->postJson("/api/v1/loans/{$loan->id}/return")
            ->assertStatus(200)
            ->assertJson(['message' => 'Buku berhasil dikembalikan.']);

        $loan->refresh();

        $this->assertEquals(2, $book->fresh()->available_copies);
        $this->assertEquals(12000, (float) $loan->fine_amount);
        $this->assertEquals('returned', $loan->status);
    }
}
