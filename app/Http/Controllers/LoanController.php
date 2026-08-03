<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Loan;
use App\Models\Member;
use App\Services\FineCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{
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
            // stok berkurang
            $book->decrement('available_copies');

            return Loan::create([
                'member_id' => $member->id,
                'book_id' => $book->id,
                'borrowed_at' => Carbon::today(),
                'due_date' => Carbon::today()->addDays(FineCalculator::LOAN_DURATION_DAYS),
                'fine_amount' => 0,
                'status' => 'active',
            ]);
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

        if ($loan->returned_at) {
            return response()->json(['message' => 'Loan ini sudah dikembalikan.'], 422);
        }

        $loan = DB::transaction(function () use ($loan) {
            // stok balik lagi
            $loan->book->increment('available_copies');

            $loan->returned_at = Carbon::today();
            $loan->fine_amount = (new FineCalculator())->calculate($loan);
            $loan->status = 'returned';
            $loan->save();

            return $loan;
        });

        return response()->json([
            'message' => 'Buku berhasil dikembalikan.',
            'loan' => $loan->load(['member', 'book']),
        ]);
    }
}
