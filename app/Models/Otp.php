<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;

class Otp extends Model
{
    //
    use HasFactory ,HasApiTokens;
    protected $table = 'otps';
    protected $fillable = ['mobile_number', 'otp', 'expire_at'];
}
