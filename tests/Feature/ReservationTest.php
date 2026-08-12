<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookReservation;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationTest extends TestCase
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

    protected function makeBook(int $available = 0): Book
    {
        return Book::factory()->create([
            'total_copies' => $available + 1,
            'available_copies' => $available,
        ]);
    }

    public function test_member_cannot_reserve_available_book(): void
    {
        $member = $this->makeMember();
        $book = $this->makeBook(available: 1);

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/reservations', ['book_id' => $book->id])
            ->assertStatus(422)
            ->assertJson(['message' => 'Buku masih tersedia, tidak perlu reservasi.']);
    }

    public function test_member_can_reserve_out_of_stock_book(): void
    {
        $member = $this->makeMember();
        $book = $this->makeBook(available: 0);

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/reservations', ['book_id' => $book->id])
            ->assertStatus(201)
            ->assertJson(['message' => 'Reservasi berhasil dibuat. Kamu masuk antrean saat buku tersedia.']);

        $this->assertDatabaseHas('book_reservations', [
            'member_id' => $member->member->id,
            'book_id' => $book->id,
            'status' => 'pending',
        ]);
    }

    public function test_duplicate_active_hold_rejected(): void
    {
        $member = $this->makeMember();
        $book = $this->makeBook(available: 0);

        BookReservation::factory()->create([
            'member_id' => $member->member->id,
            'book_id' => $book->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/reservations', ['book_id' => $book->id])
            ->assertStatus(422)
            ->assertJson(['message' => 'Member sudah menahan (reserve) buku ini.']);
    }

    public function test_sixth_active_hold_rejected(): void
    {
        $member = $this->makeMember();

        foreach (range(1, 5) as $i) {
            $book = $this->makeBook(available: 0);
            BookReservation::factory()->create([
                'member_id' => $member->member->id,
                'book_id' => $book->id,
                'status' => 'pending',
            ]);
        }

        $book = $this->makeBook(available: 0);

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/reservations', ['book_id' => $book->id])
            ->assertStatus(422)
            ->assertJson(['message' => 'Jumlah hold maksimal 5 per member.']);
    }

    public function test_suspended_member_cannot_reserve(): void
    {
        $member = $this->makeMember([], ['status' => 'suspended']);
        $book = $this->makeBook(available: 0);

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/reservations', ['book_id' => $book->id])
            ->assertStatus(422)
            ->assertJson(['message' => 'Member tidak aktif (suspended).']);
    }

    public function test_staff_can_reserve_on_behalf_of_member(): void
    {
        $member = $this->makeMember();
        $book = $this->makeBook(available: 0);
        $staff = User::factory()->create(['role' => 'staff']);

        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/reservations', [
            'book_id' => $book->id,
            'member_id' => $member->id,
        ])
            ->assertStatus(201);

        $this->assertDatabaseHas('book_reservations', [
            'member_id' => $member->member->id,
            'book_id' => $book->id,
            'status' => 'pending',
        ]);
    }

    public function test_staff_must_fill_member_id(): void
    {
        $book = $this->makeBook(available: 0);
        $staff = User::factory()->create(['role' => 'staff']);

        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/reservations', ['book_id' => $book->id])
            ->assertStatus(422)
            ->assertJson(['message' => 'Admin/staff wajib mengisi member_id.']);
    }

    public function test_member_cannot_reserve_on_behalf_of_others(): void
    {
        $member = $this->makeMember();
        $other = $this->makeMember();
        $book = $this->makeBook(available: 0);

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/reservations', [
            'book_id' => $book->id,
            'member_id' => $other->id,
        ])
            ->assertStatus(403)
            ->assertJson(['message' => 'Kamu tidak punya izin untuk reservasi atas nama member lain.']);
    }

    public function test_return_marks_first_pending_hold_ready(): void
    {
        $book = $this->makeBook(available: 0);

        $a = $this->makeMember();
        $b = $this->makeMember();

        BookReservation::factory()->create([
            'member_id' => $a->member->id,
            'book_id' => $book->id,
            'reserved_at' => now()->subDays(2),
            'status' => 'pending',
        ]);
        $bHold = BookReservation::factory()->create([
            'member_id' => $b->member->id,
            'book_id' => $book->id,
            'reserved_at' => now()->subDay(),
            'status' => 'pending',
        ]);

        $borrower = $this->makeMember();
        $loan = Loan::factory()->create([
            'member_id' => $borrower->member->id,
            'book_id' => $book->id,
            'borrowed_at' => now()->subDays(5),
            'due_date' => now()->addDays(9),
            'returned_at' => null,
            'status' => 'active',
            'fine_amount' => 0,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/loans/{$loan->id}/return")->assertStatus(200);

        $this->assertDatabaseHas('book_reservations', [
            'id' => $a->member->reservations()->first()->id,
            'status' => 'ready',
        ]);
        $this->assertDatabaseHas('book_reservations', [
            'id' => $bHold->id,
            'status' => 'pending',
        ]);
    }

    public function test_issue_fulfills_member_reservation(): void
    {
        $member = $this->makeMember();
        $book = $this->makeBook(available: 1);

        $reservation = BookReservation::factory()->create([
            'member_id' => $member->member->id,
            'book_id' => $book->id,
            'reserved_at' => now()->subDays(2),
            'status' => 'ready',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/loans/issue', [
            'member_id' => $member->member->id,
            'book_id' => $book->id,
        ])
            ->assertStatus(201);

        $this->assertDatabaseHas('book_reservations', [
            'id' => $reservation->id,
            'status' => 'fulfilled',
        ]);
        $this->assertEquals(0, $book->fresh()->available_copies);
    }

    public function test_member_can_cancel_own_reservation(): void
    {
        $member = $this->makeMember();
        $book = $this->makeBook(available: 0);

        $reservation = BookReservation::factory()->create([
            'member_id' => $member->member->id,
            'book_id' => $book->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($member);

        $this->deleteJson("/api/v1/reservations/{$reservation->id}")
            ->assertStatus(200)
            ->assertJson(['message' => 'Reservasi dibatalkan.']);

        $this->assertDatabaseHas('book_reservations', [
            'id' => $reservation->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_member_cannot_cancel_others_reservation(): void
    {
        $member = $this->makeMember();
        $other = $this->makeMember();
        $book = $this->makeBook(available: 0);

        $reservation = BookReservation::factory()->create([
            'member_id' => $other->member->id,
            'book_id' => $book->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($member);

        $this->deleteJson("/api/v1/reservations/{$reservation->id}")
            ->assertStatus(403)
            ->assertJson(['message' => 'Bukan reservasi kamu.']);
    }

    public function test_staff_can_cancel_any_reservation(): void
    {
        $member = $this->makeMember();
        $book = $this->makeBook(available: 0);

        $reservation = BookReservation::factory()->create([
            'member_id' => $member->member->id,
            'book_id' => $book->id,
            'status' => 'pending',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/reservations/{$reservation->id}")
            ->assertStatus(200);

        $this->assertDatabaseHas('book_reservations', [
            'id' => $reservation->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_finished_reservation_cannot_be_cancelled_again(): void
    {
        $member = $this->makeMember();
        $book = $this->makeBook(available: 0);

        $reservation = BookReservation::factory()->create([
            'member_id' => $member->member->id,
            'book_id' => $book->id,
            'status' => 'cancelled',
        ]);

        Sanctum::actingAs($member);

        $this->deleteJson("/api/v1/reservations/{$reservation->id}")
            ->assertStatus(422)
            ->assertJson(['message' => 'Reservasi sudah selesai atau dibatalkan sebelumnya.']);
    }

    public function test_member_only_sees_own_reservations(): void
    {
        $member = $this->makeMember();
        $other = $this->makeMember();

        $own = $this->makeBook(available: 0);
        $others = $this->makeBook(available: 0);

        BookReservation::factory()->create([
            'member_id' => $member->member->id,
            'book_id' => $own->id,
            'status' => 'pending',
        ]);
        BookReservation::factory()->create([
            'member_id' => $other->member->id,
            'book_id' => $others->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($member);

        $this->getJson('/api/v1/reservations')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['book_id' => $own->id]);
    }
}
