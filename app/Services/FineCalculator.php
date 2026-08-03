<?php

namespace App\Services;

use App\Models\Loan;
use Carbon\Carbon;

class FineCalculator
{
    // Denda per hari keterlambatan (Rupiah)
    public const FINE_PER_DAY = 2000;

    // Durasi pinjam default (hari)
    public const LOAN_DURATION_DAYS = 14;

    // Hitung denda final saat buku dikembalikan
    public function calculate(Loan $loan): float
    {
        if (!$loan->returned_at || !$loan->returned_at->greaterThan($loan->due_date)) {
            return 0;
        }

        $daysLate = (int) abs($loan->returned_at->diffInDays($loan->due_date));

        return $daysLate * self::FINE_PER_DAY;
    }

    // Estimasi denda untuk laporan (buku yang belum dikembalikan)
    public function estimate(Loan $loan): float
    {
        if ($loan->returned_at) {
            return $this->calculate($loan);
        }

        $today = Carbon::today();

        if (!$today->greaterThan($loan->due_date)) {
            return 0;
        }

        $daysLate = (int) abs($today->diffInDays($loan->due_date));

        return $daysLate * self::FINE_PER_DAY;
    }
}
