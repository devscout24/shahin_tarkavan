<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubOrganization extends Model
{
    protected $fillable = [
        'user_id',
        'organization_type_id',
    ];

    public function organizationType()
    {
        return $this->belongsTo(OrganizationType::class, 'organization_type_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
