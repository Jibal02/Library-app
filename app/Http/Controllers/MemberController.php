<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Member;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    // GET /api/v1/members/{id}/history — admin & staff
    public function history($id)
    {
        $member = Member::findOrFail($id);

        $loans = Loan::where('member_id', $member->id)
            ->with('book')
            ->orderByDesc('borrowed_at')
            ->get();

        return response()->json([
            'member' => $member,
            'loans' => $loans,
            'active_loans' => $loans->whereNull('returned_at')->values(),
            'total_loans' => $loans->count(),
        ]);
    }
}
