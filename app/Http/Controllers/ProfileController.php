<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    // GET /api/v1/profile — data user yang login + kartu member + riwayat pinjaman (kalau role member)
    public function show(Request $request)
    {
        $user = $request->user()->load('member');

        if (!$user->member) {
            return response()->json([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'loans' => [],
                'active_loans' => 0,
                'total_loans' => 0,
            ]);
        }

        $loans = Loan::where('member_id', $user->member->id)
            ->with('book')
            ->orderByDesc('borrowed_at')
            ->get();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'member_code' => $user->member->member_code,
            'status' => $user->member->status,
            'loans' => $loans,
            'active_loans' => $loans->whereNull('returned_at')->count(),
            'total_loans' => $loans->count(),
        ]);
    }

    // GET /api/v1/profile/loans — loan aktif (belum dikembalikan) milik user login
    public function loans(Request $request)
    {
        $member = $request->user()->member;

        if (!$member) {
            return response()->json([
                'loans' => [],
                'active_loans' => 0,
            ]);
        }

        $loans = Loan::where('member_id', $member->id)
            ->whereNull('returned_at')
            ->with('book')
            ->orderByDesc('borrowed_at')
            ->get();

        return response()->json([
            'loans' => $loans,
            'active_loans' => $loans->count(),
        ]);
    }

    // GET /api/v1/profile/history — riwayat semua loan milik user login
    public function history(Request $request)
    {
        $member = $request->user()->member;

        if (!$member) {
            return response()->json([
                'loans' => [],
                'total_loans' => 0,
            ]);
        }

        $loans = Loan::where('member_id', $member->id)
            ->with('book')
            ->orderByDesc('borrowed_at')
            ->get();

        return response()->json([
            'loans' => $loans,
            'total_loans' => $loans->count(),
        ]);
    }
}
