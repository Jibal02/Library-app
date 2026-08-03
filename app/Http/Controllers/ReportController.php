<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Services\FineCalculator;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    // GET /api/v1/reports/overdue — admin & staff
    public function overdue()
    {
        $calculator = new FineCalculator();

        $loans = Loan::with(['member', 'book'])
            ->whereNull('returned_at')
            ->where('due_date', '<', now()->toDateString())
            ->orderBy('due_date')
            ->get()
            ->map(function ($loan) use ($calculator) {
                $loan->estimated_fine = $calculator->estimate($loan);
                return $loan;
            });

        return response()->json([
            'count' => $loans->count(),
            'overdue_loans' => $loans,
        ]);
    }
}
