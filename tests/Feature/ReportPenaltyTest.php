<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportPenaltyTest extends TestCase
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

    protected function makeLoan(Member $member, Book $book, array $attributes = []): Loan
    {
        return Loan::create(array_merge([
            'member_id' => $member->id,
            'book_id' => $book->id,
            'borrowed_at' => Carbon::today()->subDays(30),
            'due_date' => Carbon::today()->subDays(10),
            'returned_at' => null,
            'fine_amount' => 0,
            'status' => 'overdue',
        ], $attributes));
    }

    public function test_admin_can_access_member_penalty_summary(): void
    {
        $this->makeMember();

        Sanctum::actingAs($this->makeAdmin());

        $this->getJson('/api/v1/reports/member-penalty')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'summary' => ['total_members', 'total_penalty_count', 'total_final_fine'],
                'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_member_cannot_access_member_penalty_summary(): void
    {
        Sanctum::actingAs($this->makeMember());

        $this->getJson('/api/v1/reports/member-penalty')->assertForbidden();
    }

    public function test_counts_only_late_loans_and_sums_only_returned_fines(): void
    {
        $user = $this->makeMember(['name' => 'Budi'], ['member_code' => 'MBR-1001']);
        $card = $user->member;
        $book = Book::factory()->create();

        $this->makeLoan($card, $book, [
            'returned_at' => Carbon::today()->subDays(5),
            'fine_amount' => 10000,
            'status' => 'returned',
        ]);

        $this->makeLoan($card, $book, [
            'fine_amount' => 0,
            'status' => 'overdue',
        ]);

        $this->makeLoan($card, $book, [
            'returned_at' => Carbon::today()->subDays(10),
            'fine_amount' => 0,
            'status' => 'returned',
        ]);

        $this->makeLoan($card, $book, [
            'due_date' => Carbon::today()->addDays(5),
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->makeAdmin());

        $this->getJson('/api/v1/reports/member-penalty')
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'member_id' => $user->id,
                        'member_name' => 'Budi',
                        'member_code' => 'MBR-1001',
                        'total_penalty_count' => 2,
                        'total_final_fine' => 10000,
                    ],
                ],
                'summary' => [
                    'total_members' => 1,
                    'total_penalty_count' => 2,
                    'total_final_fine' => 10000,
                ],
                'pagination' => ['total' => 1],
            ]);
    }

    public function test_member_without_penalty_is_excluded(): void
    {
        $user = $this->makeMember(['name' => 'Budi'], ['member_code' => 'MBR-1001']);
        $card = $user->member;
        $book = Book::factory()->create();

        $this->makeLoan($card, $book, [
            'returned_at' => Carbon::today()->subDays(5),
            'fine_amount' => 0,
            'status' => 'returned',
        ]);

        $this->makeMember(['name' => 'Agus'], ['member_code' => 'MBR-2002']);

        Sanctum::actingAs($this->makeAdmin());

        $this->getJson('/api/v1/reports/member-penalty')
            ->assertOk()
            ->assertJson(['summary' => ['total_members' => 1]]);
    }

    public function test_search_by_name_and_member_code(): void
    {
        $this->makeMember(['name' => 'Budi'], ['member_code' => 'MBR-1001']);
        $user2 = $this->makeMember(['name' => 'Agus'], ['member_code' => 'MBR-2002']);
        $book = Book::factory()->create();

        $this->makeLoan($user2->member, $book);

        Sanctum::actingAs($this->makeAdmin());

        $this->getJson('/api/v1/reports/member-penalty?search=MBR-2002')
            ->assertOk()
            ->assertJson(['pagination' => ['total' => 1]]);

        $this->getJson('/api/v1/reports/member-penalty?search=Agus')
            ->assertOk()
            ->assertJson(['pagination' => ['total' => 1]]);

        $this->getJson('/api/v1/reports/member-penalty?search=MBR-1001')
            ->assertOk()
            ->assertJson(['pagination' => ['total' => 0]]);
    }

    public function test_summary_uses_grand_totals_across_pages(): void
    {
        $book = Book::factory()->create();

        for ($i = 1; $i <= 3; $i++) {
            $member = $this->makeMember(['name' => "Member {$i}"], ['member_code' => "MBR-{$i}000"]);
            $this->makeLoan($member->member, $book, [
                'returned_at' => Carbon::today()->subDays(4),
                'fine_amount' => 6000,
                'status' => 'returned',
            ]);
        }

        Sanctum::actingAs($this->makeAdmin());

        $this->getJson('/api/v1/reports/member-penalty?per_page=2')
            ->assertOk()
            ->assertJson([
                'pagination' => ['per_page' => 2, 'total' => 3, 'last_page' => 2],
                'summary' => [
                    'total_members' => 3,
                    'total_penalty_count' => 3,
                    'total_final_fine' => 18000,
                ],
            ]);
    }
}
