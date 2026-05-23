<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErProgramTime extends Model
{
    use HasFactory;

    protected $fillable = [
        'er_program_id',
        'time',
        'slot_date',
        'start_time',
        'end_time',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'slot_date' => 'date',
            'is_available' => 'boolean',
        ];
    }

    public function program()
    {
        return $this->belongsTo(ErProgram::class, 'er_program_id', 'id');
    }
}
