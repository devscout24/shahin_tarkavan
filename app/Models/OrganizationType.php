<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
    ];

    public function cluborganization()
    {
        return $this->hasMany(ClubOrganization::class, 'organization_type_id', 'id');
    }

    public function clubOrganizations()
    {
        return $this->hasMany(ClubOrganization::class, 'organization_type_id', 'id');
    }
}
