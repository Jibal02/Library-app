<?php

namespace App\Console\Commands;

use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MarkOverdueLoans extends Command
{
    protected $signature = 'loans:mark-overdue';

    protected $description = 'Tandai loan yang belum dikembalikan dan sudah lewat due_date menjadi overdue';

    public function handle(): int
    {
        $updated = Loan::query()
            ->whereNull('returned_at')
            ->where('status', 'active')
            ->where('due_date', '<', Carbon::today())
            ->update(['status' => 'overdue']);

        $this->info($updated . ' loan ditandai overdue.');

        return self::SUCCESS;
    }
}
