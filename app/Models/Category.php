<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
