<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'role',
        'mobile_number',
        'country',
        'state',
        'city',
        'license_no',
        'consultation_fee',
        'lawyer_practice',
         'device_token',
        'stream_token'
    ];


    public function guests()
    {
        return $this->hasMany(Guest::class);
    }
    public function meetings()
    {
        return $this->hasMany(Meeting::class);
    }
    public function PaymentAccount()
    {
        return $this->hasMany(PaymentAccount::class);
    }
    public function attachments()
{
    return $this->hasMany(Attachment::class);
}
public function myTeamBosses()
{
    return $this->hasMany(TeamMember::class, 'team_id')->whereJsonContains('team_id', $this->id);
}
}
