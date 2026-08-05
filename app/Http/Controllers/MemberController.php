<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    // GET /api/v1/members — admin & staff, daftar user dengan role member + kartu member
    public function index(Request $request)
    {
        $members = User::where('role', 'member')
            ->with('member')
            ->when($request->q, function ($query) use ($request) {
                $query->where(function ($query) use ($request) {
                    $query->where('name', 'ilike', '%' . $request->q . '%')
                        ->orWhere('email', 'ilike', '%' . $request->q . '%')
                        ->orWhere('phone', 'ilike', '%' . $request->q . '%');
                });
            })
            ->orderBy('name')
            ->paginate(12);

        return response()->json($members);
    }

    // PATCH /api/v1/members/{id} — admin & staff, edit data member (nama/email/phone)
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->role !== 'member') {
            return response()->json([
                'message' => 'Cuma user dengan role member yang bisa diedit di sini.',
            ], 422);
        }

        $data = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email,' . $id,
            'phone' => 'required|string|max:20',
        ]);

        $user->update($data);

        if ($user->member) {
            $user->member->update($data);
        }

        return response()->json([
            'message' => 'Data member berhasil diperbarui.',
            'user' => $user->load('member'),
        ]);
    }

    // PATCH /api/v1/members/{id}/status — admin & staff, suspend/aktifkan member
    public function updateStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|in:active,suspended',
        ]);

        $user = User::findOrFail($id);

        if ($user->role !== 'member') {
            return response()->json([
                'message' => 'Cuma user dengan role member yang punya status.',
            ], 422);
        }

        $member = $user->member()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'member_code' => $this->generateMemberCode(),
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => 'active',
            ]
        );

        $member->update(['status' => $data['status']]);

        return response()->json([
            'message' => 'Status member diubah menjadi ' . $data['status'] . '.',
            'user' => $user->load('member'),
        ]);
    }

    // DELETE /api/v1/members/{id} — admin & staff, hapus user member + kartu member-nya
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        if ($user->role !== 'member') {
            return response()->json([
                'message' => 'Cuma user dengan role member yang bisa dihapus.',
            ], 422);
        }

        if ($user->member) {
            $hasActiveLoan = Loan::where('member_id', $user->member->id)
                ->whereNull('returned_at')
                ->exists();

            if ($hasActiveLoan) {
                return response()->json([
                    'message' => 'Member masih punya pinjaman aktif, tidak bisa dihapus.',
                ], 422);
            }
        }

        $user->delete();

        return response()->json([
            'message' => 'Member berhasil dihapus.',
        ]);
    }

    private function generateMemberCode(): string
    {
        do {
            $code = 'MBR-' . random_int(1000, 9999);
        } while (Member::where('member_code', $code)->exists());

        return $code;
    }

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
