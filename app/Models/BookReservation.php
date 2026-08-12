<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'book_id',
        'reserved_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'reserved_at' => 'date',
            'status' => 'string',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
