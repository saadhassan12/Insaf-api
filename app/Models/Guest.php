<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;

class Guest extends Model
{

    use HasFactory, HasApiTokens;
    protected $table = 'guests';
    protected $fillable = ['title', 'case_type', 'description', 'case_location' ,'lawyer_id'];
    
   public function user()
{
    return $this->belongsTo(User::class, 'lawyer_id');
}
  public function guest()
{
    return $this->belongsTo(User::class, 'user_id');
}
}
