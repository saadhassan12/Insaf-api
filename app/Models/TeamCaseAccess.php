<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;

class TeamCaseAccess extends Model
{
    use HasFactory ,HasApiTokens;
    protected $table = 'team_case_access'; // your actual table name

    protected $fillable = ['team_member_id', 'user_id', 'lawyer_case_id'];

    public $timestamps = true; // if you have created_at / updated_at
}
