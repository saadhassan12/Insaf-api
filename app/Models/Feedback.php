<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;

class Feedback extends Model
{


     use HasFactory, HasApiTokens;

    protected $table = 'feedback';
    protected $fillable = [
        'user_id', 'feedback_text', 'rating'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

   
}
