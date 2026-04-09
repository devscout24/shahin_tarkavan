<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErProgramGoal extends Model
{
    use HasFactory;

    protected $fillable = [
        'er_program_id',
        'goal',
    ];

    public function program()
    {
        return $this->belongsTo(ErProgram::class, 'er_program_id', 'id');
    }
}
