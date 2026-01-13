<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;

class Meeting extends Model
{
    use HasFactory, HasApiTokens;

    protected $fillable = [
        'user_id',
        'meeting_agenda',
        'location',
        'datetime',
        'attendee_name',
        'attendee_phone',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
