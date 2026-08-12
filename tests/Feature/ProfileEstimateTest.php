<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileEstimateTest extends TestCase
{
    use RefreshDatabase;

    protected function makeMember(): User
    {
        $user = User::factory()->create(['role' => 'member']);

        Member::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        return $user;
    }

    protected function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_profile_returns_estimated_fine_for_unreturned_overdue_loan(): void
    {
        $member = $this->makeMember();
        $book = Book::factory()->create();

        Loan::factory()->create([
            'member_id' => $member->member->id,
            'book_id' => $book->id,
            'borrowed_at' => now()->subDays(15),
            'due_date' => now()->subDay(),
            'returned_at' => null,
            'status' => 'active',
            'fine_amount' => 0,
        ]);

        Sanctum::actingAs($member);

        $this->getJson('/api/v1/profile/loans')
            ->assertStatus(200)
            ->assertJsonCount(1, 'loans')
            ->assertJson([
                'loans' => [[
                    'status' => 'active',
                    'fine_amount' => 0,
                    'estimated_fine' => 2000,
                ]],
            ]);
    }

    public function test_profile_history_has_final_and_estimated_fine(): void
    {
        $member = $this->makeMember();
        $book = Book::factory()->create();

        Loan::factory()->create([
            'member_id' => $member->member->id,
            'book_id' => $book->id,
            'borrowed_at' => now()->subDays(19),
            'due_date' => now()->subDays(5),
            'returned_at' => now()->subDays(2),
            'status' => 'returned',
            'fine_amount' => 6000,
        ]);

        Loan::factory()->create([
            'member_id' => $member->member->id,
            'book_id' => $book->id,
            'borrowed_at' => now()->subDays(15),
            'due_date' => now()->subDay(),
            'returned_at' => null,
            'status' => 'active',
            'fine_amount' => 0,
        ]);

        Sanctum::actingAs($member);

        $response = $this->getJson('/api/v1/profile/history')
            ->assertStatus(200)
            ->assertJsonCount(2, 'loans');

        $loans = collect($response->json('loans'));

        $this->assertSame(6000, (int) $loans->firstWhere('status', 'returned')['estimated_fine']);
        $this->assertSame(6000, (int) $loans->firstWhere('status', 'returned')['fine_amount']);
        $this->assertSame(2000, (int) $loans->firstWhere('status', 'active')['estimated_fine']);
        $this->assertSame(0, (int) $loans->firstWhere('status', 'active')['fine_amount']);
    }

    public function test_staff_loan_list_has_estimated_fine(): void
    {
        $member = $this->makeMember();
        $book = Book::factory()->create();

        Loan::factory()->create([
            'member_id' => $member->member->id,
            'book_id' => $book->id,
            'borrowed_at' => now()->subDays(15),
            'due_date' => now()->subDay(),
            'returned_at' => null,
            'status' => 'active',
            'fine_amount' => 0,
        ]);

        Sanctum::actingAs($this->makeAdmin());

        $this->getJson('/api/v1/loans')
            ->assertStatus(200)
            ->assertJsonPath('data.0.estimated_fine', 2000);
    }
}
