<?php

namespace App\Http\Controllers;


use App\Models\AddProceeding;
use App\Models\Attachment;
use App\Models\CloseCase;
use App\Models\Feedback;
use App\Models\FollowUpMeeting;
use App\Models\Guest;
use App\Models\LawyerCase;
use App\Models\Meeting;
use App\Models\PaymentAccount;
use App\Models\TeamCaseAccess;
use App\Models\TeamMember;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

use App\Notifications\FirebasePushNotification;

class LawyerController extends Controller
{
    //
public function getlawyer()
{
    try {
        $getlawyer = auth()->user();

        if (!$getlawyer) {
            return response()->json([
                'message' => 'User not authenticated',
                'status' => 401,
            ], 401);
        }

        return response()->json([
            'message' => 'Lawyer Get successful',
            'status' => 200,
            'Lawyer' => $getlawyer
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Something went wrong',
            'status' => 500,
            'error' => $e->getMessage(), 
        ], 500);
    }
}


public function lawyeraddcase(Request $request)
{
    $case = new LawyerCase();

    $case->case_number = $request->case_number;
    $case->party_a = $request->party_a;
    $case->party_b = $request->party_b;
    $case->case_type = $request->case_type;
    $case->description = $request->description;
    $case->case_location = $request->case_location;
    $case->institution_date = $request->institution_date;
    $case->task_to_be_done = $request->task_to_be_done;
    $case->court_name = $request->court_name;
    $case->judge_name = $request->judge_name;
    $case->client_name = $request->client_name;
    $case->client_phone = $request->client_phone;
    $case->client_payment_amount = $request->client_payment_amount;
    $case->client_reference_of = $request->client_reference_of;
    $case->ref_note = $request->has('ref_note') && $request->ref_note ? 1 : 0;

    $case->user_id = auth()->id();
    $case->save();

  $firebaseResponse = null;
        $deviceToken = auth()->user()->device_token;
        
        if (!empty($deviceToken)) {
            try {
                $notification = new FirebasePushNotification(
                    'New Case Created',
                    'Case #' . $case->case_number . ' has been added successfully.',
                    $deviceToken
                );
                $firebaseResponse = $notification->toFirebase();
            } catch (\Exception $e) {
                // Log the error if needed, but don't stop case creation
                \Log::error('FCM Error: ' . $e->getMessage());
                $firebaseResponse = 'Notification failed: ' . $e->getMessage();
            }
        }


    return response()->json([
        'message' => 'Case created successfully',
        'status' => 200,
        'data' => $case,
        'firebase_response' => $firebaseResponse
    ], 201);
}

    
    

public function updateCase(Request $request, $id)
{
    $case = LawyerCase::findOrFail($id);

    // Optional: Check if current user is the owner
    if ($case->user_id !== auth()->id()) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    // Validation rules for fields that *may* come in request
    $rules = [
        'case_number' => 'sometimes|required|string',
        'party_a' => 'sometimes|required|string',
        'party_b' => 'sometimes|required|string',
        'case_type' => 'sometimes|required|string',
        'description' => 'sometimes|nullable|string',
        'case_location' => 'sometimes|required|string',
        'institution_date' => 'sometimes|required|date',
        'task_to_be_done' => 'sometimes|nullable|string',
        'court_name' => 'sometimes|required|string',
        'judge_name' => 'sometimes|nullable|string',
        'client_name' => 'sometimes|nullable|string',
        'client_phone' => 'sometimes|nullable|string',
        'client_payment_amount' => 'sometimes|nullable|numeric',
        'client_reference_of' => 'sometimes|nullable|string',
        'ref_note' => 'sometimes|nullable|boolean',
    ];

    $validated = $request->validate($rules);

    // Set default ref_note if present
    if ($request->has('ref_note')) {
        $validated['ref_note'] = $request->ref_note ? 1 : 0;
    }

    // Only update provided fields
    $case->update($validated);
    
    
    
    $firebaseResponse = null;
        $deviceToken = auth()->user()->device_token;
        
        if (!empty($deviceToken)) {
            try {
                $notification = new FirebasePushNotification(
                    'New Case Created',
                    'Case #' . $case->case_number . ' has been updated successfully.',
                    $deviceToken
                );
                $firebaseResponse = $notification->toFirebase();
            } catch (\Exception $e) {
                // Log the error if needed, but don't stop case creation
                \Log::error('FCM Error: ' . $e->getMessage());
                $firebaseResponse = 'Notification failed: ' . $e->getMessage();
            }
        }
    
    return response()->json([
        'message' => 'Case updated successfully.',
        'status' => 200,
        'data' => $case,
        'firebase_response' => $firebaseResponse
    ]);
}
public function lawyergetcase(Request $request)
{
    $authId = Auth::id();

    $teamCaseIds = TeamCaseAccess::where('user_id', $authId)->pluck('lawyer_case_id')->toArray();
    // dd($teamCaseIds);

    $ownAndTaggedCases = LawyerCase::where(function ($q) use ($authId) {
        $q->where('user_id', $authId)
          ->orWhereRaw('JSON_CONTAINS(lawyer_ids, ?)', [json_encode((int)$authId)]);
    })->pluck('id')->toArray();

    $allCaseIds = array_unique(array_merge($teamCaseIds, $ownAndTaggedCases));

    $query = LawyerCase::with('user', 'proceedings')->whereIn('id', $allCaseIds);

    $LawyerCase = $query->get();

    if ($request->filled('case_number')) {
        $input = $request->case_number;
        $numberOnly = preg_replace('/\D/', '', $input);
        $normalizedInput = ltrim($numberOnly, '0');
        $inputLower = Str::lower($input);

        $LawyerCase = $LawyerCase->filter(function ($case) use ($normalizedInput, $inputLower) {
            if (
                Str::contains(Str::lower($case->case_number), $inputLower) ||
                Str::contains(Str::lower((string) $case->client_reference_of), $inputLower) ||
                Str::contains(Str::lower($case->case_type), $inputLower)
            ) {
                return true;
            }

            if ($case->user) {
                $lawyer = $case->user;
                $dbMobile = ltrim(preg_replace('/\D/', '', $lawyer->mobile_number), '0');

                if (
                    Str::endsWith($dbMobile, $normalizedInput) ||
                    Str::contains(Str::lower($lawyer->first_name), $inputLower) ||
                    Str::contains(Str::lower($lawyer->last_name), $inputLower)
                ) {
                    return true;
                }
            }

            if (is_array($case->lawyer_ids)) {
                $lawyers = \App\Models\User::whereIn('id', $case->lawyer_ids)->get();

                foreach ($lawyers as $lawyer) {
                    $dbMobile = ltrim(preg_replace('/\D/', '', $lawyer->mobile_number), '0');

                    if (
                        Str::endsWith($dbMobile, $normalizedInput) ||
                        Str::contains(Str::lower($lawyer->first_name), $inputLower) ||
                        Str::contains(Str::lower($lawyer->last_name), $inputLower)
                    ) {
                        return true;
                    }
                }
            }

            return false;
        })->values()->unique('id');
    }

    // Step 5: Append lawyer_users attribute if needed
    $LawyerCase->each(function ($case) {
        $case->setAttribute('lawyer_users', $case->lawyer_users);
    });

    $LawyerCase = $LawyerCase->unique('id')->values();

    // Final API response
    return response()->json([
        'message' => 'Lawyer Case(s) fetched successfully',
        'status' => 200,
        'data' => $LawyerCase
    ]);
}



    

        
public function assignLawyersToCase(Request $request, $caseId)
{
    $request->validate([
        'lawyer_ids' => 'required|array',
        'lawyer_ids.*' => 'exists:users,id'
    ]);

    $case = LawyerCase::findOrFail($caseId);

    // Decode old lawyer_ids
    $existingLawyerIds = $case->lawyer_ids ?? [];
    if (is_string($existingLawyerIds)) {
        $existingLawyerIds = json_decode($existingLawyerIds, true);
    }

    // Merge with new ones
    $mergedLawyers = array_unique(array_merge($existingLawyerIds, $request->lawyer_ids));

    // Save
    $case->lawyer_ids = array_values($mergedLawyers);
    $case->save();

    $newlyAdded = array_diff($request->lawyer_ids, $existingLawyerIds);
  $users = \App\Models\User::whereIn('id', $newlyAdded)
    ->whereNotNull('device_token')
    ->get();

foreach ($users as $user) {
    try {
        $notification = new FirebasePushNotification(
            'New Case Assigned',
            'You have been added to Case #' . $case->case_number,
            $user->device_token
        );

        $notification->toFirebase();

    } catch (\Exception $e) {
        // Log the error so you know which user failed
        \Log::error("FCM Notification failed for user ID {$user->id}: " . $e->getMessage());
        // Optionally continue silently
        continue;
    }
}   


    return response()->json([
        'message' => 'Lawyers added successfully to the case.',
        'status' => 200,
        'data' => $case,

    ]);
}


        
public function removeSingleLawyerFromCase($caseId, $lawyerId)
{
    $case = LawyerCase::findOrFail($caseId);

    $lawyerIds = $case->lawyer_ids ?? [];

    if (is_string($lawyerIds)) {
        $lawyerIds = json_decode($lawyerIds, true);
    }

    // Filter out the removed lawyer
    $updatedLawyerIds = array_filter($lawyerIds, function ($id) use ($lawyerId) {
        return $id != $lawyerId;
    });

    $case->lawyer_ids = array_values($updatedLawyerIds); // Reset keys
    $case->save();

    // ✅ Send notification to removed lawyer
   $removedLawyer = \App\Models\User::find($lawyerId);

if ($removedLawyer && $removedLawyer->device_token) {
    try {
        $notification = new FirebasePushNotification(
            'Removed from Case',
            'You have been removed from Case #' . $case->case_number,
            $removedLawyer->device_token
        );
        $notification->toFirebase(); // Send the notification
    } catch (\Exception $e) {
        \Log::error("FCM notification failed for removed lawyer ID {$removedLawyer->id}: " . $e->getMessage());
        // Optional: continue silently
    }
}


    return response()->json([
        'message' => 'Lawyer removed from the case successfully.',
        'status' => 200,
        'data' => $case
        
    ]);
}


public function getCaseLawyersWithDetails($caseId)
{
    $case = LawyerCase::findOrFail($caseId);

    // Decode lawyer_ids if it's stored as a JSON string
    $lawyerIds = [];

    if (is_string($case->lawyer_ids)) {
        $decoded = json_decode($case->lawyer_ids, true);
        $lawyerIds = is_array($decoded) ? $decoded : [];
    } elseif (is_array($case->lawyer_ids)) {
        $lawyerIds = $case->lawyer_ids;
    }

    // Remove empty values just in case
    $lawyerIds = array_filter($lawyerIds, fn($id) => !empty($id));
    $lawyers = !empty($lawyerIds)
        ? \App\Models\User::whereIn('id', $lawyerIds)->get()
        : collect();

    $caseData = $case->toArray();
    $caseData['lawyers'] = $lawyers;

    return response()->json([
        'message' => 'Lawyers fetched successfully for the case.',
        'status' => 200,
        'data' => $caseData
    ]);
}



    public function deletecase($id)
    {
        $meeting = LawyerCase::where('id', $id)
            ->where('user_id', auth()->id())
            ->delete();

        if (!$meeting) {
            return response()->json(['message' => 'Only the team lead is allowed to delete this Case.'], 404);
        }

        return response()->json(['message' => 'Lawyer Case Delete successfully', 'status' => 200, 'data' => $meeting], 200);
    }

public function meetingstore(Request $request)
{
    $validated = $request->validate([
        'meeting_agenda' => 'required|string',
        'location' => 'required|string',
        'datetime' => 'required|date',
        'attendee_name' => 'required|string',
        'attendee_phone' => 'nullable',
    ]);

    $validated['user_id'] = auth()->id();

    $meeting = Meeting::create($validated);

    // ✅ Send push notification to logged-in user if device_token exists
    $user = auth()->user();
  $firebaseResponse = null;

if ($user->device_token) {
    try {
        $notification = new FirebasePushNotification(
            'New Meeting Scheduled',
            'Meeting on "' . $meeting->meeting_agenda . '" has been scheduled at ' . $meeting->location . ' on ' . \Carbon\Carbon::parse($meeting->datetime)->format('d M Y h:i A'),
            $user->device_token
        );
        $firebaseResponse = $notification->toFirebase();
    } catch (\Exception $e) {
        // Optional: Log the error
        \Log::error('Firebase notification failed: ' . $e->getMessage());
        $firebaseResponse = 'Notification failed';
    }
}

return response()->json([
    'message' => 'Meeting created successfully',
    'status' => 200,
    'data' => $meeting,
    'firebase_response' => $firebaseResponse
], 201);

}



public function getmeeting()
{
    $search = request()->query('meeting_agenda');

    $query = Meeting::with('user')
        ->where('user_id', auth()->id());

    if ($search) {
        $numberOnly = preg_replace('/\D/', '', $search);

        $query->where(function ($q) use ($search, $numberOnly) {
            $q->where('meeting_agenda', 'like', '%' . $search . '%')
              ->orWhere('attendee_name', 'like', '%' . $search . '%');

            if (is_numeric($numberOnly) && strlen($numberOnly) >= 10) {
                $normalizedInput = ltrim($numberOnly, '0');

                $q->orWhereRaw("REPLACE(REPLACE(REPLACE(attendee_phone, '-', ''), ' ', ''), '+', '') LIKE ?", ["%{$normalizedInput}%"]);
            }
        });
    }

    $meetings = $query->get();

    if ($meetings->isEmpty()) {
        return response()->json([
            'message' => 'No meetings found for the given search criteria',
            'status' => 200,
            'data' => null
        ]);
    }

    return response()->json([
        'message' => 'Meetings retrieved successfully',
        'status' => 200,
        'data' => $meetings
    ]);
}



public function meetingupdate(Request $request, $id)
{
    $validated = $request->validate([
        'meeting_agenda' => 'required|string',
        'location' => 'required|string',
        'datetime' => 'required|date',
        'attendee_name' => 'required|string',
        'attendee_phone' => 'nullable',
    ]);

    // Meeting ko find karo
    $meeting = Meeting::findOrFail($id);

    // Update fields
    $meeting->update($validated);

    return response()->json([
        'message' => 'Meeting updated successfully',
        'status' => 200,
        'data' => $meeting
    ]);
}

    public function deletemeeting($id)
    {
        $meeting = Meeting::where('id', $id)
            ->where('user_id', auth()->id())
            ->delete();

        if (!$meeting) {
            return response()->json(['message' => 'Meeting not found'], 404);
        }

        return response()->json(['message' => 'Meeting Delete successfully', 'status' => 200, 'data' => $meeting], 200);
    }

    public function paymentstore(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'account_title' => 'required|string',
            'bank_name' => 'required|string',
            'account_number' => 'required|string',
            'iban' => 'required|string',
        ]);
        $validated['user_id'] = auth()->id();
        $paymentAccount = PaymentAccount::create($validated);

        return response()->json([
            'message' => 'Payment account created successfully.',
            'status' => 200,
            'data' => $paymentAccount
        ], 201);
    }

    public function getpayment()
    {

        $meeting = PaymentAccount::with('user')
            ->where('user_id', auth()->id())
            ->get();

        if (!$meeting) {
            return response()->json(['message' => 'Payment Account not found'], 404);
        }

        return response()->json(['message' => 'Payment Account Get successfully', 'status' => 200, 'data' => $meeting], 200);
    }

    public function deletepayment($id)
    {
        $meeting = PaymentAccount::where('id', $id)
            ->where('user_id', auth()->id())
            ->delete();

        if (!$meeting) {
            return response()->json(['message' => 'payment Account not found'], 404);
        }

        return response()->json(['message' => 'Payment Account Delete successfully', 'status' => 200, 'data' => $meeting], 200);
    }
public function lawyeraddproceeding(Request $request)
{
    $validated = $request->validate([
        'case_id' => 'required|exists:lawyercases,id',
        'note' => 'required|string',
        'datetime' => 'required|date',
        'judge_name' => 'nullable|string',
    ]);

    $validated['user_id'] = auth()->id();

    // 1. Create proceeding
    $proceeding = AddProceeding::create($validated);

    // 2. Update related case institution_date and judge_name
    $dateOnly = date('Y-m-d', strtotime($request->datetime));
    \DB::table('lawyercases')
        ->where('id', $request->case_id)
        ->update([
            'institution_date' => $dateOnly,
            'judge_name' => $request->judge_name,
        ]);

 // 3. Send Firebase Notification to the case owner
$case = \App\Models\LawyerCase::find($request->case_id);
$owner = \App\Models\User::find($case->user_id);
$firebaseResponse = null;

if ($owner && $owner->device_token) {
    try {
        $notification = new FirebasePushNotification(
            'New Proceeding Added',
            'A new proceeding has been added for Case #' . $case->case_number,
            $owner->device_token
        );
        $firebaseResponse = $notification->toFirebase();
    } catch (\Exception $e) {
        \Log::error('Firebase notification failed: ' . $e->getMessage());
        $firebaseResponse = 'Notification failed';
    }
}

// 4. Return response
return response()->json([
    'message' => 'Proceeding added successfully.',
    'status' => 200,
    'data' => $proceeding,
    'firebase_response' => $firebaseResponse,
], 201);

}


public function getProceedingByCaseId($case_id)
{
    $proceeding = AddProceeding::where('case_id', $case_id)->with('case','user')->get();

    if (!$proceeding) {
        return response()->json(['message' => 'Proceeding not found'], 404);
    }

    return response()->json([
        'message' => 'Proceeding fetched successfully',
        'status' => 200,
        'data' => $proceeding
    ]);
}    


public function updateProceedingByCaseId(Request $request, $id)
{
    $validated = $request->validate([
        'note' => 'required|string',
        'datetime' => 'required|date',
        'judge_name' => 'nullable|string',
    ]);

    $proceeding = AddProceeding::where('id', $id)->first();

    if (!$proceeding) {
        return response()->json(['message' => 'Proceeding not found'], 404);
    }

    $proceeding->update([
        'note' => $validated['note'],
        'datetime' => $validated['datetime'],
        'judge_name' => $validated['judge_name'] ?? null,
    ]);

    
    $dateOnly = date('Y-m-d', strtotime($validated['datetime']));
    \DB::table('lawyercases')
        ->where('id', $proceeding->case_id)
        ->update([
            'institution_date' => $dateOnly,
            'judge_name' => $validated['judge_name']?? null,
        ]);

    return response()->json([
        'message' => 'Proceeding updated successfully',
        'status' => 200,
        'data' => $proceeding
    ]);
}


         public function closecase(Request $request)
        {
            $validated = $request->validate([
                'case_id' => 'required|exists:lawyercases,id',
                'note' => 'required|string',
            ]);
        
            $validated['user_id'] = auth()->id();
            $closeCase = CloseCase::create($validated);
            LawyerCase::where('id', $validated['case_id'])->update(['close_status' => 1]);
        
            // Load relations
            $closeCase->load(['lawyerCase', 'user']);
        
            return response()->json([
                'message' => 'Case closed successfully.',
                'status' => 200,
                'data' => $closeCase
            ], 201);
        }


public function getclosecase()
{
    $keyword = request()->query('case_number');

    $cases = CloseCase::with(['lawyerCase', 'user'])
        ->where('user_id', auth()->id())
        ->where(function ($q) use ($keyword) {
            if ($keyword) {
                // Normalize keyword for mobile number
                $mobile1 = $keyword;
                $mobile2 = null;

                if (Str::startsWith($keyword, '03')) {
                    // Convert 03xxxxxxxxx to +923xxxxxxxxx
                    $mobile2 = preg_replace('/^03/', '+92', $keyword);
                } elseif (Str::startsWith($keyword, '+92')) {
                    // Convert +923xxxxxxxxx to 03xxxxxxxxx
                    $mobile2 = preg_replace('/^\+92/', '03', $keyword);
                }

                $q->whereHas('lawyerCase', function ($query) use ($keyword) {
                    $query->where('case_number', 'like', '%' . $keyword . '%')
                          ->orWhere('case_type', 'like', '%' . $keyword . '%');
                });

                $q->orWhereHas('user', function ($query) use ($mobile1, $mobile2) {
                    $query->where('mobile_number', 'like', '%' . $mobile1 . '%');

                    if ($mobile2) {
                        $query->orWhere('mobile_number', 'like', '%' . $mobile2 . '%');
                    }
                });
            }
        })
        ->get();

    return response()->json([
        'message' => 'Closed cases retrieved successfully.',
        'status' => 200,
        'data' => $cases
    ]);
}





    public function followmeetingstore(Request $request)
    {
        $validated = $request->validate([
            'meeting_id' => 'required|exists:meetings,id',
            'meeting_agenda' => 'required|string',
            'datetime' => 'required|date',
            'location' => 'required|string',
        ]);

        $validated['user_id'] = auth()->id();
        $followUp = FollowUpMeeting::create($validated);

        $followUp->load(['user', 'meeting']);

        return response()->json([
            'message' => 'Follow-up meeting added successfully.',
            'status' => 200,
            'data' => $followUp
        ], 201);
    }

    public function followmeetingget()
    {
        $data = FollowUpMeeting::with(['user', 'meeting'])->where('user_id', auth()->id())->get();
        return response()->json([
            'message' => 'Get Follow-up meeting added successfully.',
            'status' => 200,
            'data' => $data
        ]);
    }
    
 public function attachment(Request $request)
{
    $request->validate([
        'case_id' => 'required|integer',
        'upload_file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
    ]);

    $user = auth()->user();
    $caseId = $request->case_id;
    $hasAccess = TeamCaseAccess::where('user_id', $user->id)
                    ->where('lawyer_case_id', $caseId)
                    ->exists();

    $isOwner = LawyerCase::where('id', $caseId)
                ->where('user_id', $user->id)
                ->exists();

    $isTagged = LawyerCase::where('id', $caseId)
                ->whereRaw('JSON_CONTAINS(lawyer_ids, ?)', [json_encode((int)$user->id)])
                ->exists();

    if (!($hasAccess || $isOwner || $isTagged)) {
        return response()->json([
            'message' => 'You do not have permission to upload attachment for this case.',
        ], 403);
    }

    // ✅ Proceed with upload
    if ($request->hasFile('upload_file')) {
        $file = $request->file('upload_file');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $destinationPath = public_path('uploads/attachments');

        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        $file->move($destinationPath, $fileName);

        $attachment = Attachment::create([
            'user_id' => $user->id,
            'case_id' => $caseId,
            'upload_file' => 'uploads/attachments/' . $fileName,
        ]);

        return response()->json([
            'message' => 'Attachment uploaded successfully.',
            'attachment' => $attachment,
        ], 200);
    }

    return response()->json([
        'message' => 'No file uploaded.'
    ], 400);
}



public function updateAttachment(Request $request, $id)
{
    $request->validate([
        'upload_file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
    ]);

    $attachment = Attachment::findOrFail($id);
    $oldFilePath = public_path($attachment->upload_file);
    if (file_exists($oldFilePath)) {
        unlink($oldFilePath);
    }
    $file = $request->file('upload_file');
    $fileName = time() . '_' . $file->getClientOriginalName();
    $destinationPath = public_path('uploads/attachments');

    if (!file_exists($destinationPath)) {
        mkdir($destinationPath, 0755, true);
    }

    $file->move($destinationPath, $fileName);

    // Update attachment record
    $attachment->upload_file = 'uploads/attachments/' . $fileName;
    $attachment->save();

    return response()->json([
        'message' => 'Attachment updated successfully.',
         'status' => 200,
        'attachment' => $attachment,
    ], 200);
}
public function deleteAttachment($id)
{
    $attachment = Attachment::findOrFail($id);

    $filePath = public_path($attachment->upload_file);

    if (file_exists($filePath)) {
        unlink($filePath);
    }

    // Record ko DB se delete karein
    $attachment->delete();

    return response()->json([
        'message' => 'Attachment deleted successfully.',
         'status' => 200,
    ], 200);
}
public function getattachment($caseId)
{
    $authId = Auth::id();
    $case = \App\Models\LawyerCase::where('id', $caseId)
        ->where(function ($q) use ($authId) {
            $q->where('user_id', $authId)
              ->orWhereRaw('JSON_CONTAINS(lawyer_ids, ?)', [json_encode((int)$authId)]);
        })
        ->first();


        
       $data = \App\Models\Attachment::with(['user', 'case'])
        ->where('case_id', $caseId)
        ->get();
 

    return response()->json([
        'message' => 'Get Attachment successfully.',
        'status' => 200,
        'data' => $data
    ]);
}
    
    public function getbyrequest()
    {
           $guests = Guest::with('user','guest')->where('status_accpet', 0)->where('status_reject', 0)->where('lawyer_id', auth()->id())->get();
        if ($guests) {
            return response()->json(['message' => 'Guest case Get successfully',  'status' => 200, 'data' => $guests]);
        }
    }
public function accept($id)
{
    $guest = Guest::find($id);

    if (!$guest) {
        return response()->json(['message' => 'Guest not found', 'status' => 404]);
    }

    // Update accept status
    $guest->status_accpet = 1;
    $guest->save();

    // Firebase Push Notification
$firebaseResponse = null;
$user = \App\Models\User::find($guest->user_id); // get the related user

if ($user && $user->device_token) {
    try {
        $notification = new FirebasePushNotification(
            'Invitation Accepted',
            'You have been accepted as a Lawyer.',
            $user->device_token
        );

        $firebaseResponse = $notification->toFirebase();
    } catch (\Exception $e) {
        \Log::error('Firebase notification failed: ' . $e->getMessage());
        $firebaseResponse = 'Notification failed';
    }
}

return response()->json([
    'message' => 'Guest accepted successfully',
    'status' => 200,
    'data' => $guest,
    'firebase_response' => $firebaseResponse
]);

}

      public function reject($id)
{
    $guest = Guest::find($id);

    if (!$guest) {
        return response()->json(['message' => 'Guest not found', 'status' => 404]);
    }

    $guest->status_reject = 1;
    $guest->save();

    // Firebase Push Notification
    $firebaseResponse = null;
    $user = \App\Models\User::find($guest->user_id); // or adjust to match your relation

    if ($user && $user->device_token) {
        try {
            $notification = new FirebasePushNotification(
                'Invitation Rejected',
                'Your Lawyer request has been rejected.',
                $user->device_token
            );

            $firebaseResponse = $notification->toFirebase();
        } catch (\Exception $e) {
            \Log::error('Firebase notification failed: ' . $e->getMessage());
            $firebaseResponse = 'Notification failed';
        }
    }

    return response()->json([
        'message' => 'Guest rejected successfully',
        'status' => 200,
        'data' => $guest,
        'firebase_response' => $firebaseResponse
    ]);
}


         
public function my_team(Request $request)
{
    // Step 1: Validate input
    $request->validate([
        'team_id' => 'required|array',
    ]);

    $user = auth()->user();
    $teamIdsArray = $request->team_id;
    $teamIds = json_encode($teamIdsArray); 

    // Step 2: Save team
    $team = TeamMember::create([
        'user_id' => $user->id,
        'team_id' => $teamIds,
    ]);



$firebaseResponses = [];

// ✅ Notify the creator
if ($user->device_token) {
    try {
        $notification = new FirebasePushNotification(
            'Team Updated',
            'You have successfully added your team.',
            $user->device_token
        );
        $firebaseResponses['creator'] = $notification->toFirebase();
    } catch (\Exception $e) {
        Log::error('Firebase notification to creator failed: ' . $e->getMessage());
        $firebaseResponses['creator'] = 'Notification failed';
    }
}

// ✅ Notify each added team member
foreach ($teamIdsArray as $memberId) {
    $member = \App\Models\User::find($memberId);

    if ($member && $member->device_token) {
        try {
            $notification = new FirebasePushNotification(
                'You were added to a team',
                'You have been added by ' . $user->first_name . ' to their team.',
                $member->device_token
            );
            $firebaseResponses["user_{$memberId}"] = $notification->toFirebase();
        } catch (\Exception $e) {
            Log::error("Firebase notification to user ID {$memberId} failed: " . $e->getMessage());
            $firebaseResponses["user_{$memberId}"] = 'Notification failed';
        }
    }
}

// ✅ Final response
return response()->json([
    'message' => 'Team IDs saved and notifications sent.',
    'status' => 200,
    'firebase_responses' => $firebaseResponses,
    'data' => $team
]);

}



public function leaveTeam(Request $request, $id)
{
    $request->validate([
        'team_id' => 'required|integer',
    ]);

    $authUser = auth()->user();
    $userIdToRemove = $request->team_id;

    // Step 1: Get team
    $team = TeamMember::findOrFail($id);
    $teamIds = is_array(json_decode($team->team_id, true)) ? json_decode($team->team_id, true) : [];

    $updatedTeamIds = array_filter($teamIds, function ($teamId) use ($userIdToRemove) {
        return $teamId != $userIdToRemove;
    });
    $updatedTeamIds = array_values($updatedTeamIds); 

    \App\Models\TeamCaseAccess::where('user_id', $userIdToRemove)
        ->where('team_member_id', $id)
        ->delete();

    // Step 4: Save updated team_ids
    $team->team_id = json_encode($updatedTeamIds);
    $team->save();

    // Step 5: If self-removal, delete related records
    if ($authUser->id == $userIdToRemove) {
        // Delete all access records for this team ID
        \App\Models\TeamCaseAccess::where('team_member_id', $id)->delete();

        // Delete team membership row
        \App\Models\TeamMember::where('user_id', $authUser->id)->where('id',$id)->delete();

        return response()->json([
            'message' => 'You have left the team. All your access, memberships, and related data have been removed.',
            'status' => 200,
            'self_removed' => true,
        ]);
    }



$removedUser = \App\Models\User::find($userIdToRemove);
$firebaseResponse = null;

if ($removedUser && $removedUser->device_token) {
    try {
        $notification = new FirebasePushNotification(
            'Removed from Team',
            'You have been removed from a team by ' . $authUser->first_name . '.',
            $removedUser->device_token
        );
        $firebaseResponse = $notification->toFirebase();
    } catch (\Exception $e) {
        Log::error('Failed to send Firebase notification (Removed from Team): ' . $e->getMessage());
        $firebaseResponse = 'Notification failed';
    }
}

// Step 7: Final API response
return response()->json([
    'message' => 'Team member removed successfully.',
    'status' => 200,
    'firebase_response' => $firebaseResponse,
    'self_removed' => false,
    'data' => $team
]);

}








public function getteam()
{
    $authUser = auth()->user();

    $memberTeams = TeamMember::whereJsonContains('team_id', (int)$authUser->id)->get();
    if ($memberTeams->isEmpty()) {
        $allTeams = TeamMember::all();
        $memberTeams = $allTeams->filter(function ($team) use ($authUser) {
            $ids = json_decode($team->team_id, true);
            return is_array($ids) && in_array($authUser->id, $ids);
        });
    }

    $adminTeams = TeamMember::where('user_id', $authUser->id)->get();
    $allTeams = $memberTeams->merge($adminTeams)->unique('id');
    $result = [];
    foreach ($allTeams as $team) {
        $memberIds = json_decode($team->team_id, true);
        $teamUsers = is_array($memberIds)
            ? User::whereIn('id', $memberIds)->get()
            : collect();
        $role = ($team->user_id === $authUser->id) ? 'admin' : 'member';

        $result[] = [
            'team_id' => (string) $team->id,
            'user_id'=>$authUser->id,
            'user' => User::find($team->user_id),
            'team_users' => $teamUsers,
         
        ];
    }

    return response()->json([
        'message' => 'Teams where you are a member or admin fetched successfully.',
        'status' => 200,
        'teamMembers' => $result,
    ]);
}




public function transaction(Request $request)
{
    $request->validate([
        'case_id' => 'required|integer',
        'transaction_purpose' => 'required',
        'payment_account' => 'required',
        'trancaction_title' => 'required',
        'amount' => 'required',
        'image' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
    ]);

    $user = auth()->user();

    // File Upload (optional)
    $filePath = null;

    if ($request->hasFile('image')) {
        $file = $request->file('image');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $destinationPath = public_path('uploads/transactions');

        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        $file->move($destinationPath, $fileName);
        $filePath = 'uploads/transactions/' . $fileName;
    }

    // Save transaction with uploaded file path (if exists)
    $transaction = Transaction::create([
        'user_id' => $user->id,
        'case_id' => $request->case_id,
        'transaction_purpose' => $request->transaction_purpose,
        'amount' => $request->amount,
        'trancaction_title' => $request->trancaction_title,
        'payment_account' => $request->payment_account,
        'image' => $filePath, // Will be null if no file uploaded
    ]);

    return response()->json([
        'message' => 'Transaction uploaded successfully.',
        'status' => 200,
        'transaction' => $transaction,
    ], 200);
}

public function updateTransaction(Request $request, $id)
{
    $request->validate([
        'case_id' => 'required|integer',
        'transaction_purpose' => 'required',
        'payment_account'=> 'required',
        'trancaction_title'=>'required',
        'amount'=> 'required',
        'image' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
    ]);

    $transaction = Transaction::findOrFail($id);

    // Check if a new file is uploaded
    if ($request->hasFile('image')) {
        // Delete old file if exists
        $oldFilePath = public_path($transaction->image);
        if ($transaction->image && file_exists($oldFilePath)) {
            unlink($oldFilePath);
        }

        // Upload new file
        $file = $request->file('image');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $destinationPath = public_path('uploads/transactions');

        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        $file->move($destinationPath, $fileName);
        $transaction->image = 'uploads/transactions/' . $fileName;
    } else {
        // No file uploaded, set image to null
        $transaction->image = null;
    }

    // Update other fields
    $transaction->case_id = $request->case_id;
    $transaction->transaction_purpose = $request->transaction_purpose;
    $transaction->payment_account = $request->payment_account;
    $transaction->trancaction_title = $request->trancaction_title;
    $transaction->amount = $request->amount;
    $transaction->save();

    return response()->json([
        'message' => 'Transaction updated successfully.',
        'status' => 200,
        'transaction' => $transaction,
    ], 200);
}



public function deleteTransaction($id)
{
    $transaction = Transaction::findOrFail($id);

    $transaction->delete();

    return response()->json([
        'message' => 'Transaction deleted successfully.',
        'status' => 200
    ], 200);
}

public function gettransaction($id)
    {
        $data = Transaction::with(['user', 'case'])->where('case_id',$id)->where('user_id', auth()->id())->get();
        return response()->json([
            'message' => 'Get Transaction successfully.',
            'status' => 200,
            'data' => $data
        ]);
    }
    
    


public function getsearch(Request $request)
{
    $query = User::where('role', 'lawyer');

    if ($request->filled('name')) {
        $search = $request->name;
        $inputNumber = preg_replace('/\D/', '', $search); 

        if (strlen($inputNumber) >= 10 && is_numeric($inputNumber)) {
            
            if (Str::startsWith($inputNumber, '0')) {
                $inputNumber = '92' . substr($inputNumber, 1);
            } elseif (Str::startsWith($inputNumber, '3')) {
                $inputNumber = '92' . $inputNumber;
            }
            $query->whereRaw("
                REPLACE(REPLACE(REPLACE(REPLACE(mobile_number, '+', ''), '-', ''), ' ', ''), '(', '') 
                LIKE ?
            ", ["%$inputNumber%"]);
        } else {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
            });
        }
    }
    $users = $query->get();

    return response()->json([
        'message' => 'Lawyer search successfully.',
        'status' => 200,
        'data' => $users
    ]);
}







public function submitFeedback(Request $request)
{
    
    $validated = $request->validate([
        'feedback_text' => 'required|string',
        'rating' => 'nullable|integer|min:1|max:5',
    ]);

    $feedback = Feedback::create([
        'user_id' => auth()->id(),
        'feedback_text' => $request->feedback_text,
        'rating' => $request->rating,
    ]);

    return response()->json([
        'message' => 'Feedback submitted successfully.',
           'status' => 200,
        'data' => $feedback
    ]);
}

public function getUserFeedback()
{
    $user = auth()->user();

    $feedbacks = Feedback::where('user_id', $user->id)->with('user')->get();

    return response()->json([
        'message' => 'User feedback retrieved successfully.',
        'data' => $feedbacks,
        'status' => 200
    ]);
}

}
