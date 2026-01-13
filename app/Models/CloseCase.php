<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;

class CloseCase extends Model
{
    //
    use HasFactory, HasApiTokens;

    protected $table = 'close_case';

    protected $fillable = [
        'user_id',
        'case_id',
        'note',
    ];

    public function lawyerCase()
    {
        return $this->belongsTo(LawyerCase::class, 'case_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
