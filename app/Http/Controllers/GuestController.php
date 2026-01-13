<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Meeting;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

use App\Notifications\FirebasePushNotification;

class GuestController extends Controller
{
    //
  public function store(Request $request)
{
    $request->validate([
        'title' => 'required|string|max:255',
        'case_type' => 'required|string|max:255',
        'description' => 'nullable|string',
        'case_location' => 'required|string|max:255',
        'lawyer_id' => 'nullable', // ✅ validation added
    ]);

    $guest = new Guest();
    $guest->user_id = auth()->id();
    $guest->title = $request->title;
    $guest->case_type = $request->case_type;
    $guest->description = $request->description;
    $guest->case_location = $request->case_location;
    $guest->lawyer_id = $request->lawyer_id;
    $guest->save();
    
    
 $firebaseResponse = null;

if ($request->filled('lawyer_id')) {
    $lawyer = \App\Models\User::find($request->lawyer_id);

    if ($lawyer && $lawyer->device_token) {
        try {
            $notification = new FirebasePushNotification(
                'New Guest Case Assigned',
                'A new guest case titled "' . $guest->title . '" has been assigned to you.',
                $lawyer->device_token
            );
            $firebaseResponse = $notification->toFirebase();
        } catch (\Exception $e) {
            \Log::error('Firebase notification failed for lawyer ID: ' . $lawyer->id . ' — ' . $e->getMessage());
            $firebaseResponse = 'Notification failed';
        }
    }
}


    return response()->json([
        'message' => 'Guest case saved successfully',
        'status' => 200,
        'data' => $guest,
        'firebase_response' => $firebaseResponse
    ]);
}




public function getByDate()
{
    $query = Guest::with('user')->where('user_id', auth()->id());

    $guests = $query->get();

    // 🔍 If `title` is present, use it as the global search
    if (request()->filled('title')) {
        $search = request('title');
        $normalizedSearch = ltrim(preg_replace('/[^0-9]/', '', $search), '0');

        $guests = $guests->filter(function ($guest) use ($search, $normalizedSearch) {
            $guestTitle = strtolower($guest->title ?? '');

            if (Str::contains($guestTitle, strtolower($search))) {
                return true;
            }

            if (!$guest->user) return false;

            $user = $guest->user;
            $first = strtolower($user->first_name ?? '');
            $last = strtolower($user->last_name ?? '');
            $fullName = $first . ' ' . $last;

            if (
                Str::contains($first, strtolower($search)) ||
                Str::contains($last, strtolower($search)) ||
                Str::contains($fullName, strtolower($search))
            ) {
                return true;
            }

            $dbMobile = ltrim(preg_replace('/[^0-9]/', '', $user->mobile_number ?? ''), '0');
            if (Str::endsWith($dbMobile, $normalizedSearch)) {
                return true;
            }

            return false;
        })->values();
    }

    return response()->json([
        'message' => 'Guest cases fetched successfully',
        'status' => 200,
        'data' => $guests
    ]);
}




public function getLawyers(Request $request)
{
    $query = User::where('role', 'lawyer');

    if ($request->filled('mobile_number')) {
        $inputNumber = $request->mobile_number;

        // Clean input: remove space, dash etc.
        $inputNumber = str_replace([' ', '-', '(', ')'], '', $inputNumber);

        // Normalize to +92 format
        $normalized = $this->normalizeMobileNumber($inputNumber);

        // Match exactly with normalized number
        $query->where('mobile_number', $normalized);
    }

    $lawyers = $query->get();

    return response()->json([
        'message' => 'Lawyers fetched successfully',
        'status' => 200,
        'data' => $lawyers
    ]);
}

private function normalizeMobileNumber($number)
{
    // If starts with 03..., convert to +92...
    if (preg_match('/^03\d{9}$/', $number)) {
        return '+92' . substr($number, 1);
    }

    // If already in +92 format
    if (preg_match('/^\+92\d{10}$/', $number)) {
        return $number;
    }

    // Handle if someone enters just digits: 92307...
    if (preg_match('/^92\d{10}$/', $number)) {
        return '+' . $number;
    }

    // Otherwise return as is
    return $number;
}




    public function getrequest()
    {

        $guests = Guest::where('lawyer_id', 0)
            ->with('user')
            ->where('user_id', auth()->id())
            ->get();
        if ($guests) {
            return response()->json(['message' => 'Guest case Get successfully',  'status' => 200, 'data' => $guests]);
        }
    }
    
      public function transaction(Request $request)
{
    $request->validate([
        'case_id' => 'required|integer',
        'transaction_purpose' => 'required',
        'payment_account'=> 'required',
        'trancaction_title'=>'required',
        'amount'=> 'required',
    ]);

      $user = auth()->user();
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'case_id' => $request->case_id,
            'transaction_purpose' => $request->transaction_purpose,
            'amount'=> $request->amount,
            'trancaction_title'=> $request->trancaction_title,
            'payment_account' => $request->payment_account,
            
        ]);

        return response()->json([
            'message' => 'Transaction uploaded successfully.',
            'status' => 200,
            'attachment' => $transaction,
        ], 200);

}


public function gettransaction($id)
    {
        $data = Transaction::with(['user', 'caseguest'])->where('case_id',$id)->where('user_id', auth()->id())->get();
        return response()->json([
            'message' => 'Get Transaction successfully.',
            'status' => 200,
            'data' => $data
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

public function meetingstore(Request $request)
{
    $validated = $request->validate([
        'meeting_agenda' => 'required|string',
        'location' => 'required|string',
        'datetime' => 'required|date',
        'attendee_name' => 'required|string',
        'attendee_phone' => 'nullable|string',
    ]);

    $validated['user_id'] = auth()->id();

    $meeting = Meeting::create($validated);

  

$title = 'New Meeting Scheduled';
$body = 'Meeting on "' . $meeting->meeting_agenda . '" at ' . $meeting->location . ' on ' . \Carbon\Carbon::parse($meeting->datetime)->format('d M Y h:i A');

$firebaseResponse = [];

// ✅ Notify the creator (logged-in user)
$creator = auth()->user();
if ($creator && $creator->device_token) {
    try {
        $notification = new FirebasePushNotification($title, $body, $creator->device_token);
        $firebaseResponse['creator'] = $notification->toFirebase();
    } catch (\Exception $e) {
        Log::error('Firebase notification to creator failed: ' . $e->getMessage());
        $firebaseResponse['creator'] = 'Notification failed';
    }
}

// ✅ Notify attendee (if phone matches a registered user)
if ($request->filled('attendee_phone')) {
    $attendeeUser = \App\Models\User::where('mobile_number', $request->attendee_phone)->first();
    if ($attendeeUser && $attendeeUser->device_token) {
        try {
            $notification = new FirebasePushNotification($title, $body, $attendeeUser->device_token);
            $firebaseResponse['attendee'] = $notification->toFirebase();
        } catch (\Exception $e) {
            Log::error('Firebase notification to attendee failed: ' . $e->getMessage());
            $firebaseResponse['attendee'] = 'Notification failed';
        }
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
}
