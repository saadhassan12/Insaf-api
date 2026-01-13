<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;
use App\Models\LawyerCase;


class TeamMember extends Model
{
    //
    use HasFactory ,HasApiTokens;

    protected $table = 'teams';

    protected $fillable = [
        'user_id',
        'team_id',
    ];
  protected $casts = [
        'team_id' => 'array', // team_id JSON array ke roop mein cast hoga
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
     public function team()
    {
        return $this->belongsTo(User::class, 'team_id');
    }
    
    protected static function booted()
{
    static::created(function ($team) {
        $creatorId = $team->user_id;

        // ✅ Decode team_id (JSON string) to array
        $memberIds = json_decode($team->team_id, true);

        if (!is_array($memberIds)) {
            return; // handle error if needed
        }

        // Get all cases created by the creator
        $cases = LawyerCase::where('user_id', $creatorId)->get();

        foreach ($memberIds as $memberId) {
            foreach ($cases as $case) {
                TeamCaseAccess::create([
                    'team_member_id' => $team->id,
                    'user_id' => $memberId,
                    'lawyer_case_id' => $case->id,
                ]);
            }
        }
    });
}

}
