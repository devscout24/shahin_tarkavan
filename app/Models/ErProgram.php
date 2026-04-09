<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErProgram extends Model
{
    use HasFactory;

    protected $fillable = [
        'coach_id',
        'user_id',
        'sport',
        'program_name',
        'program_price',
        'program_location',
        'program_start',
        'program_end',
        'about_program',
        'discount_price',
        'upto_age',
        'program_photo',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'program_price' => 'decimal:2',
            'discount_price' => 'decimal:2',
            'upto_age' => 'integer',
            'program_start' => 'date',
            'program_end' => 'date',
        ];
    }

    public function coach()
    {
        return $this->belongsTo(Coach::class, 'coach_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function times()
    {
        return $this->hasMany(ErProgramTime::class, 'er_program_id', 'id');
    }

    public function goals()
    {
        return $this->hasMany(ErProgramGoal::class, 'er_program_id', 'id');
    }

    public function purchases()
    {
        return $this->hasMany(ErProgramPurchase::class, 'er_program_id', 'id');
    }

    public function reviews()
    {
        return $this->hasMany(ErProgramReview::class, 'er_program_id', 'id');
    }
}
