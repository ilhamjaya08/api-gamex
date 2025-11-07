<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'kode',
        'nama',
        'keterangan',
        'harga',
        'status',
        'category_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'boolean',
        'harga' => 'decimal:2',
        'category_id' => 'integer',
    ];

    /**
     * Get transactions associated with this product.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
