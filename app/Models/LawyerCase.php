<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\AddProceeding;
use App\Models\Attachment;
use App\Models\TeamCaseAccess;

class LawyerCase extends Model
{
    use HasFactory, HasApiTokens;

    protected $table = 'lawyercases';

    protected $fillable = [
        'user_id',
        'case_number',
        'party_a',
        'party_b',
        'case_type',
        'description',
        'case_location',
        'institution_date',
        'task_to_be_done',
        'court_name',
        'judge_name',
        'client_name',
        'client_phone',
        'client_payment_amount',
        'client_reference_of',
        'lawyer_ids',
        'ref_note',
    ];
    

  
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function proceedings()
    {
        return $this->hasMany(AddProceeding::class, 'case_id');
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class,'case_id');
    }

  
    protected static function booted()
    {
        static::created(function ($case) {
            $creatorId = $case->user_id;

            $teams = TeamMember::where('user_id', $creatorId)->get();

            foreach ($teams as $team) {
                $memberIds = json_decode($team->team_id, true);

                if (!is_array($memberIds)) continue;

                foreach ($memberIds as $memberId) {
                    $alreadyExists = TeamCaseAccess::where('lawyer_case_id', $case->id)
                        ->where('user_id', $memberId)
                        ->exists();

                    if (!$alreadyExists) {
                        TeamCaseAccess::create([
                            'team_member_id' => $team->id,
                            'user_id' => $memberId,
                            'lawyer_case_id' => $case->id,
                        ]);
                    }
                }
            }
        });
    }
}
