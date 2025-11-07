<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deposit extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'random_amount',
        'total_amount',
        'qris_code',
        'qris_image',
        'status',
        'paid_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Deposit belongs to a user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
