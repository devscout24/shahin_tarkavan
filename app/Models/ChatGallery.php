<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatGallery extends Model
{
    protected $fillable = [
        'chat_id',
        'image',
    ];

    public function chat()
    {
        return $this->belongsTo(Chat::class, 'chat_id');
    }
}
