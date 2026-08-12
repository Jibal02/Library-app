<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookReservation;
use App\Models\Loan;
use App\Models\Member;
use App\Services\FineCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LoanController extends Controller
{
    // GET /api/v1/loans — admin & staff, daftar loan dengan filter (book_id/status/member_id)
    public function index(Request $request)
    {
        $loans = Loan::with(['member', 'book'])
            ->when($request->book_id, function ($query) use ($request) {
                $query->where('book_id', $request->book_id);
            })
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->member_id, function ($query) use ($request) {
                $query->where('member_id', $request->member_id);
            })
            ->orderByDesc('borrowed_at')
            ->paginate(12);

        $loans->getCollection()->transform(function ($loan) {
            $loan->estimated_fine = (new FineCalculator)->estimate($loan);

            return $loan;
        });

        return response()->json($loans);
    }

    // GET /api/v1/transactions?date=YYYY-MM-DD — admin & staff, peminjaman + pengembalian di tanggal itu
    public function transactions(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        $date = $request->date;

        $issued = Loan::with(['member', 'book'])
            ->where('borrowed_at', $date)
            ->where('status', 'active')
            ->orderByDesc('borrowed_at')
            ->get();

        $returned = Loan::with(['member', 'book'])
            ->where('returned_at', $date)
            ->where('status', 'returned')
            ->orderByDesc('returned_at')
            ->get();

        return response()->json([
            'date' => $date,
            'issued' => $issued,
            'returned' => $returned,
        ]);
    }

    // POST /api/v1/loans/issue — admin & staff
    public function issue(Request $request)
    {
        $data = $request->validate([
            'member_id' => 'required|exists:members,id',
            'book_id' => 'required|exists:books,id',
        ]);

        $member = Member::findOrFail($data['member_id']);
        $book = Book::findOrFail($data['book_id']);

        if ($member->status !== 'active') {
            return response()->json(['message' => 'Member tidak aktif (suspended).'], 422);
        }

        if ($book->available_copies <= 0) {
            return response()->json(['message' => 'Buku sedang habis / tidak tersedia.'], 422);
        }

        $alreadyBorrowed = Loan::where('member_id', $member->id)
            ->where('book_id', $book->id)
            ->whereNull('returned_at')
            ->exists();

        if ($alreadyBorrowed) {
            return response()->json(['message' => 'Member masih meminjam buku ini.'], 422);
        }

        $loan = DB::transaction(function () use ($member, $book) {
            // lock row buku biar 2 request bareng gak oversell stok
            $book = Book::whereKey($book->id)->lockForUpdate()->firstOrFail();

            if ($book->available_copies <= 0) {
                throw new HttpException(422, 'Buku sedang habis / tidak tersedia.');
            }

            // stok berkurang
            $book->decrement('available_copies');

            $loan = Loan::create([
                'member_id' => $member->id,
                'book_id' => $book->id,
                'borrowed_at' => Carbon::today(),
                'due_date' => Carbon::today()->addDays(FineCalculator::LOAN_DURATION_DAYS),
                'fine_amount' => 0,
                'status' => 'active',
            ]);

            // hold pending/ready milik member utk buku ini terpenuhi
            $reservation = BookReservation::where('member_id', $member->id)
                ->where('book_id', $book->id)
                ->whereIn('status', ['pending', 'ready'])
                ->orderBy('reserved_at')
                ->orderBy('id')
                ->first();

            if ($reservation) {
                $reservation->update(['status' => 'fulfilled']);
            }

            return $loan;
        });

        return response()->json([
            'message' => 'Peminjaman berhasil diterbitkan.',
            'loan' => $loan->load(['member', 'book']),
        ], 201);
    }

    // POST /api/v1/loans/{id}/return — admin & staff
    public function return(Request $request, $id)
    {
        $loan = Loan::findOrFail($id);

        if ($loan->returned_at || $loan->status === 'returned') {
            return response()->json(['message' => 'Buku ini sudah dikembalikan sebelumnya.'], 422);
        }

        $loan = DB::transaction(function () use ($loan) {
            // stok balik lagi
            $loan->book->increment('available_copies');

            $loan->returned_at = Carbon::today();
            $loan->fine_amount = (new FineCalculator)->calculate($loan);
            $loan->status = 'returned';
            $loan->save();

            // hold pending pertama (FIFO) utk buku ini jadi 'ready' = siap diambil staff
            $reservation = BookReservation::where('book_id', $loan->book_id)
                ->where('status', 'pending')
                ->orderBy('reserved_at')
                ->orderBy('id')
                ->first();

            if ($reservation) {
                $reservation->update(['status' => 'ready']);
            }

            return $loan;
        });

        return response()->json([
            'message' => 'Buku berhasil dikembalikan.',
            'loan' => $loan->load(['member', 'book']),
        ]);
    }
}
