<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;

class Transaction extends Model
{
     use HasFactory ,HasApiTokens;
     protected $table = 'transaction';
    protected $fillable = ['user_id', 'case_id', 'payment_account','trancaction_title','amount','transaction_purpose','image'];
    public function user()
{
    return $this->belongsTo(User::class);
}

public function case()
{
    return $this->belongsTo(LawyerCase::class);
}

public function caseguest()
{
    return $this->belongsTo(Guest::class);
}
    
}
