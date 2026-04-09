<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'last_name',
        'phone',
        'address',
        'profile_image',
        'email',
        'password',
        'is_verified',
        'otp',
        'otp_expires_at',
        'otp_verified_at',
        'reset_password_token',
        'reset_password_token_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'otp_expires_at' => 'datetime',
            'otp_verified_at' => 'datetime',
            'reset_password_token_expires_at' => 'datetime',
            'is_verified' => 'boolean',
        ];
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function parentAggrement()
    {
        return $this->hasOne(ParentAggrements::class, 'user_id', 'id');
    }

    public function athleteProfile()
    {
        return $this->hasOne(AthleteProfiles::class, 'user_id', 'id');
    }

    public function coachProfile()
    {
        return $this->hasOne(Coach::class, 'user_id', 'id');
    }

    public function otpCodes()
    {
        return $this->hasMany(OtpCode::class, 'user_id', 'id');
    }

    public function childrenProfiles()
    {
        return $this->hasMany(AthleteProfiles::class, 'parent_id', 'id');
    }

    public function coachingTitles()
    {
        return $this->hasMany(CoachingTitle::class, 'user_id', 'id');
    }

    public function coachMedia()
    {
        return $this->hasMany(CoachMedia::class, 'user_id', 'id');
    }

    public function programPurchases()
    {
        return $this->hasMany(ErProgramPurchase::class, 'user_id', 'id');
    }

    public function programReviews()
    {
        return $this->hasMany(ErProgramReview::class, 'user_id', 'id');
    }

    public function programs()
    {
        return $this->hasMany(ErProgram::class, 'user_id', 'id');
    }

    public function endorsementsGiven()
    {
        return $this->hasMany(Endorse::class, 'endorced_by', 'id');
    }

    public function club()
    {
        return $this->hasOne(ClubProfile::class, 'user_id', 'id');
    }

    public function cluborganization()
    {
        return $this->hasMany(ClubOrganization::class, 'user_id', 'id');
    }

    public function clubOrganizations()
    {
        return $this->hasMany(ClubOrganization::class, 'user_id', 'id');
    }

    public function clubTeams()
    {
        return $this->hasMany(ClubTeam::class, 'club_id', 'id');
    }

    public function clubSubscriptions()
    {
        return $this->hasMany(ClubSubscription::class, 'club_id', 'id');
    }

    public function clubRecruitments()
    {
        return $this->hasMany(ClubRecruitment::class, 'club_id', 'id');
    }

    public function recruitementApplies()
    {
        return $this->hasMany(RecruitementApply::class, 'user_id', 'id');
    }

    public function clubRecruitementApplies()
    {
        return $this->hasMany(RecruitementApply::class, 'club_id', 'id');
    }

    public function teamPlayersAsPlayer()
    {
        return $this->hasMany(TeamPlayer::class, 'player_id', 'id');
    }

    public function teamPlayersAsParent()
    {
        return $this->hasMany(TeamPlayer::class, 'parent_id', 'id');
    }

    public function teamPlayersAsCoach()
    {
        return $this->hasMany(TeamPlayer::class, 'coach_id', 'id');
    }

    public function teamPlayersAsClub()
    {
        return $this->hasMany(TeamPlayer::class, 'club_id', 'id');
    }
}
