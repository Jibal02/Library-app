<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\User;
use App\Services\FineCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    // GET /api/v1/reports/member-penalty — admin & staff, ringkasan denda final & keterlambatan per member
    public function memberPenaltySummary(Request $request)
    {
        $perPage = min(max((int) $request->input('per_page', 10), 1), 100);
        $search = $request->input('search');

        $penalty = Loan::select(
            'member_id',
            DB::raw('COUNT(*) as total_penalty_count'),
            DB::raw("SUM(CASE WHEN status = 'returned' THEN COALESCE(fine_amount, 0) ELSE 0 END) as total_final_fine")
        )
            ->where(function ($query) {
                $query->where('status', 'overdue')
                    ->orWhere(function ($q) {
                        $q->where('status', 'returned')
                            ->whereColumn('returned_at', '>', 'due_date');
                    });
            })
            ->groupBy('member_id');

        $query = User::query()
            ->join('members', 'users.id', '=', 'members.user_id')
            ->joinSub($penalty, 'penalty', function ($join) {
                $join->on('members.id', '=', 'penalty.member_id');
            })
            ->select(
                'users.id as member_id',
                'users.name as member_name',
                'members.member_code',
                'penalty.total_penalty_count',
                'penalty.total_final_fine'
            )
            ->orderByDesc('penalty.total_penalty_count');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereLike('users.name', "%{$search}%")
                    ->orWhereLike('members.member_code', "%{$search}%");
            });
        }

        $summary = [
            'total_members' => $query->count(),
            'total_penalty_count' => (int) $query->sum('penalty.total_penalty_count'),
            'total_final_fine' => (int) $query->sum('penalty.total_final_fine'),
        ];

        $data = $query->paginate($perPage);

        $data->getCollection()->transform(function ($row) {
            $row->total_penalty_count = (int) $row->total_penalty_count;
            $row->total_final_fine = (int) $row->total_final_fine;

            return $row;
        });

        return response()->json([
            'data' => $data->items(),
            'summary' => $summary,
            'pagination' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
            ],
        ]);
    }
}
