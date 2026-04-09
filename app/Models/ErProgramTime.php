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
    ];

    public function program()
    {
        return $this->belongsTo(ErProgram::class, 'er_program_id', 'id');
    }
}
