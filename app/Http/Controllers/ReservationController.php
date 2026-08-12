<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookReservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    private const ACTIVE_STATUSES = ['pending', 'ready'];

    private const MAX_ACTIVE_HOLDS = 5;

    // POST /api/v1/reservations — member (self) atau admin/staff (buat member)
    public function store(Request $request)
    {
        $data = $request->validate([
            'book_id' => 'required|exists:books,id',
            'member_id' => 'nullable|exists:users,id',
        ]);

        $book = Book::findOrFail($data['book_id']);

        // tentukan member target
        if (! empty($data['member_id'])) {
            if (! in_array($request->user()->role, ['admin', 'staff'])) {
                return response()->json(['message' => 'Kamu tidak punya izin untuk reservasi atas nama member lain.'], 403);
            }

            $user = User::findOrFail($data['member_id']);
            $member = $user->member;

            if (! $member) {
                return response()->json(['message' => 'User ini belum punya kartu member.'], 422);
            }
        } else {
            if ($request->user()->role !== 'member') {
                return response()->json(['message' => 'Admin/staff wajib mengisi member_id.'], 422);
            }

            $member = $request->user()->member;

            if (! $member) {
                return response()->json(['message' => 'Kamu belum punya kartu member.'], 422);
            }
        }

        if ($member->status !== 'active') {
            return response()->json(['message' => 'Member tidak aktif (suspended).'], 422);
        }

        if ($book->available_copies > 0) {
            return response()->json(['message' => 'Buku masih tersedia, tidak perlu reservasi.'], 422);
        }

        $existing = BookReservation::where('member_id', $member->id)
            ->where('book_id', $book->id)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->exists();

        if ($existing) {
            return response()->json(['message' => 'Member sudah menahan (reserve) buku ini.'], 422);
        }

        $activeHolds = BookReservation::where('member_id', $member->id)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->count();

        if ($activeHolds >= self::MAX_ACTIVE_HOLDS) {
            return response()->json(['message' => 'Jumlah hold maksimal '.self::MAX_ACTIVE_HOLDS.' per member.'], 422);
        }

        $reservation = BookReservation::create([
            'member_id' => $member->id,
            'book_id' => $book->id,
            'reserved_at' => Carbon::today(),
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Reservasi berhasil dibuat. Kamu masuk antrean saat buku tersedia.',
            'reservation' => $reservation->load(['member', 'book']),
        ], 201);
    }

    // GET /api/v1/reservations — admin/staff semua (dengan filter), member punya sendiri
    public function index(Request $request)
    {
        $query = BookReservation::with(['member', 'book']);

        if ($request->user()->role === 'member') {
            $query->where('member_id', $request->user()->member?->id);
        } else {
            $query->when($request->status, fn ($q) => $q->where('status', $request->status))
                ->when($request->book_id, fn ($q) => $q->where('book_id', $request->book_id))
                ->when($request->member_id, fn ($q) => $q->where('member_id', $request->member_id));
        }

        return response()->json(
            $query->orderByDesc('reserved_at')->paginate(12)
        );
    }

    // DELETE /api/v1/reservations/{id} — cancel (member punya sendiri / admin-staff siapa saja)
    public function destroy(Request $request, $id)
    {
        $reservation = BookReservation::findOrFail($id);

        if (! in_array($request->user()->role, ['admin', 'staff'])) {
            if ($request->user()->member?->id !== $reservation->member_id) {
                return response()->json(['message' => 'Bukan reservasi kamu.'], 403);
            }
        }

        if (in_array($reservation->status, ['fulfilled', 'cancelled'])) {
            return response()->json(['message' => 'Reservasi sudah selesai atau dibatalkan sebelumnya.'], 422);
        }

        $reservation->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Reservasi dibatalkan.',
            'reservation' => $reservation->load(['member', 'book']),
        ]);
    }
}
