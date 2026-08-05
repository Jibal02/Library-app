<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // Fitur Register
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'phone' => 'required|string|max:20'
        ]);

        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone
            ]);

            // user yang daftar (role member) otomatis dapat kartu member
            Member::create([
                'user_id' => $user->id,
                'member_code' => $this->generateMemberCode(),
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => 'active',
            ]);

            return $user;
        });

        return response()->json([
            'message' => 'User berhasil didaftarkan!',
            'user' => $user->load('member')
        ], 201);
    }

    private function generateMemberCode(): string
    {
        do {
            $code = 'MBR-' . random_int(1000, 9999);
        } while (Member::where('member_code', $code)->exists());

        return $code;
    }

    // Fitur Login
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        // Cek apakah user ada dan password cocok
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Email atau Password salah!'
            ], 401);
        }

        // cek status member yang kena suspend
        if ($user->role === 'member' && $user->member && $user->member->status === 'suspended') {
            return response()->json([
                'message' => 'Akun kamu kena suspend. Hubungi admin.'
            ], 403);
        }

        // Buat Kunci Token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login sukses!',
            'access_token' => $token,
            'user' => [        
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role, 
        ]  
        ]);
    }
}