<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;

class FollowUpMeeting extends Model
{
    //
    use HasFactory, HasApiTokens;
    protected $table = 'follow_up_meeting';
    protected $fillable = [
        'user_id',
        'meeting_id',
        'meeting_agenda',
        'datetime',
        'location'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function meeting()
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }
}
