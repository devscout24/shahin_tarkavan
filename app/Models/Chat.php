<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'conversation_id',
        'is_read',
        'message',
        'image',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function chatimage()
    {
        return $this->hasOne(ChatGallery::class, 'chat_id');
    }
}
