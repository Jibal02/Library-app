<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;

class ResyncBookStock extends Command
{
    protected $signature = 'books:resync-stock';

    protected $description = 'Sinkronkan available_copies semua buku dengan jumlah loan aktif (belum dikembalikan)';

    public function handle(): int
    {
        $fixed = Book::resyncStock();

        if ($fixed > 0) {
            $this->info($fixed.' buku diperbaiki available_copies-nya.');
        } else {
            $this->info('Semua stok buku sudah konsisten.');
        }

        return self::SUCCESS;
    }
}
