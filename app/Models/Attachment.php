<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;

class Attachment extends Model
{
     use HasFactory ,HasApiTokens;
     protected $table = 'attachment';
    protected $fillable = ['user_id', 'case_id', 'upload_file'];
    
    public function user()
{
    return $this->belongsTo(User::class);
}

public function case()
{
    return $this->belongsTo(LawyerCase::class,'case_id');
}
    
}
