<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErProgramPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'er_program_id',
        'user_id',
        'amount',
        'payment_status',
        'purchased_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'purchased_at' => 'datetime',
        ];
    }

    public function program()
    {
        return $this->belongsTo(ErProgram::class, 'er_program_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
