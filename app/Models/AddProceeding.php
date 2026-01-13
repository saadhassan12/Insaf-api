<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;

class AddProceeding extends Model
{
    //
    use HasFactory ,HasApiTokens;

    protected $table = 'add_proceeding';

    protected $fillable = [
        'user_id',
        'case_id',
        'note',
        'datetime',
        'judge_name',
    ];
    public function case()
{
    return $this->belongsTo(LawyerCase::class, 'case_id');
}
 public function user()
    {
        return $this->belongsTo(User::class);
    }
}
