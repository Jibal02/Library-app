<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoMemberSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'demo.member@example.com';

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Demo Member',
                'email' => $email,
                'password' => Hash::make('password'),
                'phone' => '081200000000',
                'role' => 'member',
            ]
        );

        $member = Member::firstOrCreate(
            ['user_id' => $user->id],
            [
                'member_code' => $this->generateMemberCode(),
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => 'active',
            ]
        );

        $book = Book::first();

        if (! $book) {
            $this->command->error('Tidak ada buku di database. Jalankan DatabaseSeeder dulu.');

            return;
        }

        // hapus loan demo yang sudah ada biar seeder bisa diulang konsisten
        Loan::where('member_id', $member->id)->delete();

        Loan::create([
            'member_id' => $member->id,
            'book_id' => $book->id,
            'borrowed_at' => Carbon::today()->subDays(20),
            'due_date' => Carbon::today()->subDays(6),
            'returned_at' => Carbon::today()->subDays(1),
            'fine_amount' => 5 * 2000, // 10.000 — 5 hari telat
            'status' => 'returned',
        ]);

        Loan::create([
            'member_id' => $member->id,
            'book_id' => $book->id,
            'borrowed_at' => Carbon::today()->subDays(19),
            'due_date' => Carbon::today()->subDays(5),
            'returned_at' => null,
            'fine_amount' => 0,
            'status' => 'overdue', // saat return nanti kehitung 5 * 2000 = 10.000
        ]);

        Loan::create([
            'member_id' => $member->id,
            'book_id' => $book->id,
            'borrowed_at' => Carbon::today()->subDays(3),
            'due_date' => Carbon::today()->addDays(11),
            'returned_at' => null,
            'fine_amount' => 0,
            'status' => 'active',
        ]);

        $this->createReturnDemoLoans($member->id);

        Book::resyncStock();

        $this->command->info('Demo member siap — login: '.$email.' / password');
        $this->command->info('user_id: '.$user->id.' | member_id (kartu): '.$member->id);
    }

    private function createReturnDemoLoans(int $memberId): void
    {
        $target = Book::find(15) ?? Book::where('total_copies', '>=', 2)->first();

        if (! $target) {
            $this->command->warn('Tidak ada buku untuk loan demo return, lewati.');

            return;
        }

        for ($i = 1; $i <= 2; $i++) {
            Loan::create([
                'member_id' => $memberId,
                'book_id' => $target->id,
                'borrowed_at' => Carbon::today()->subDays(20),
                'due_date' => Carbon::today()->subDays(6),
                'returned_at' => null,
                'fine_amount' => 0,
                'status' => 'active',
            ]);
        }

        $this->command->info('2 loan aktif (due_date lewat) dibuat utk buku id '.$target->id.' — siap diuji return.');
    }

    private function generateMemberCode(): string
    {
        do {
            $code = 'MBR-'.random_int(1000, 9999);
        } while (Member::where('member_code', $code)->exists());

        return $code;
    }
}
