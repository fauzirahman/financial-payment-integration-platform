<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'customer_number',
        'name',
        'email',
        'phone',
        'status',
    ];

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}