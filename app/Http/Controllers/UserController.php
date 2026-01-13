<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Otp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use GetStream\StreamChat\Client as StreamChatClient;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    //
    public function signup(Request $request)
    {
        
        $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'role' => 'required|in:guest,lawyer',
            'mobile_number' => 'required|regex:/^(\+92)[0-9]{10}$/',
    
            // Optional fields for all
            'country' => 'nullable|string',
            'state' => 'nullable|string',
            'city' => 'nullable|string',
    
            // Fields required only for lawyer
            'license_no' => 'required_if:role,lawyer',
            'consultation_fee' => 'required_if:role,lawyer|numeric',
            'lawyer_practice' => 'required_if:role,lawyer|string',
        ]);
    
        if ($request->mobile_number) {
            $mobileExists = User::where('mobile_number', $request->mobile_number)->exists();
            if ($mobileExists) {
                return response()->json([
                    'message' => 'Mobile number already exists.'
                ], 422);
            }
        }
    
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'mobile_number' => $request->mobile_number,
            'role' => $request->role,
            'country' => $request->country,
            'state' => $request->state,
            'city' => $request->city,
            'license_no' => $request->license_no,
            'consultation_fee' => $request->consultation_fee,
            'lawyer_practice' => $request->lawyer_practice,
        ]);
    
        $otp = rand(1000, 9999);
        // Specific numbers par fixed OTP set karo
    $specialNumbers = ['03333333333', '03111111111', '+923333333333', '+923111111111','+923400423649','+923070627751'];

    if (in_array($request->mobile_number, $specialNumbers)) {
        $otp = 1122;
    }
        
        Otp::updateOrCreate(
            ['mobile_number' => $request->mobile_number],
            ['otp' => $otp, 'expire_at' => now()->addMinutes(10)]
        );
    
        $this->sendOtpToUser($request->mobile_number, $otp);
    
        return response()->json([
            'message' => 'User created. OTP sent.',
            'status' => 200,
            'user' => $user
        ]);
    }
    

public function verifyOtp(Request $request)
{
    $request->validate([
        'mobile_number' => 'required',
        'otp' => 'required',
        'device_token' => 'nullable',
    ]);

    $otpRecord = Otp::where('mobile_number', $request->mobile_number)->first();

    if (!$otpRecord || $otpRecord->otp !== $request->otp || $otpRecord->expire_at < now()) {
        return response()->json(['message' => 'Invalid or expired OTP'], 400);
    }

    $user = User::where('mobile_number', $request->mobile_number)->first();

    // Create Laravel Passport Token
    $token = $user->createToken('auth_token')->accessToken;

    // Save device token
    $user->device_token = $request->device_token ?? 'default_token';
    $user->save();

    // ✅ Initialize Stream Client
    $streamClient = new StreamChatClient(
        config('stream-chat.key'),
        config('stream-chat.secret')
    );

    try {
        
        $streamClient->upsertUser([
            'id' => (string) $user->id,
            'name' => $user->first_name . ' ' . $user->last_name,
            'username' => 'user_' . $user->id, 
        ]);
    } catch (\Exception $e) {
        \Log::error('Stream upsert error: ' . $e->getMessage());
    }

    $streamToken = $streamClient->createToken((string) $user->id);
    $user->stream_token = $streamToken;
    $user->save();
    
    $otherUserId = '999';
    try {
        $streamClient->upsertUser([
            'id' => $otherUserId,
            'name' => 'Support Bot',
            'username' => 'support_bot',
        ]);

        $channel = $streamClient->channel('messaging', 'chat-' . $user->id . '-' . $otherUserId, [
            'members' => [(string) $user->id, $otherUserId],
        ]);
        $channel->create((string) $user->id);

        // ✅ Send welcome message
        $channel->sendMessage([
            'text' => 'Welcome to Stream Chat, ' . $user->first_name . '!',
        ], (string) $user->id);
    } catch (\Exception $e) {
        \Log::error('Stream channel/message error: ' . $e->getMessage());
    }

    return response()->json([
        'message' => 'OTP verified successfully',
        'status' => 200,
        'user' => $user,
        'token' => $token,
        'stream_token' => $streamToken,
        'stream_key' => config('stream-chat.key'),
    ]);
}


        public function login(Request $request)
        {
    
            $request->validate([
                'mobile_number' => 'required|regex:/^(\+92)[0-9]{10}$/',
            ]);
    
            $user = User::where('mobile_number', $request->mobile_number)->first();
            if (!$user) {
                return response()->json([
                    'message' => 'Mobile number not found. Please sign up first.'
                ], 404);
            }
    
            $otp = rand(1000, 9999);
            // $otp = 1122;
            
              // Specific numbers par fixed OTP set karo
            $specialNumbers = ['03333333333', '03111111111', '+923333333333', '+923111111111','+923400423649','+923070627751'];
        
            if (in_array($request->mobile_number, $specialNumbers)) {
                $otp = 1122;
            }
            Otp::updateOrCreate(
                ['mobile_number' => $request->mobile_number],
                ['otp' => $otp, 'expire_at' => now()->addMinutes()]
            );
    
            $this->sendOtpToUser($request->mobile_number, $otp);
    
            return response()->json([
                'message' => 'OTP has been sent to your mobile number.',
                'status'=>200,
                'user' => $user
            ]);
        }

    private function sendOtpToUser($number, $otp)
    {
        $hash = env('VEEVOTECH_HASH');
        $text = "Your OTP code is $otp";
        $url = "https://api.veevotech.com/v3/sendsms?hash=$hash&receivernum=$number&sendernum=INSAFE&textmessage=" . urlencode($text);
        $ch = curl_init();
        $timeout = 30;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            dd('Curl error: ' . curl_error($ch));
        } else {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode == 200) {
                $decodedResponse = json_decode($response, true);
                if (isset($decodedResponse['STATUS']) && $decodedResponse['STATUS'] == 'ERROR') {
                    return response()->json([
                        'message' => 'Failed to send OTP. Error: ' . $decodedResponse['ERROR_DESCRIPTION']
                    ], 400);
                } else {
                    
                    return response()->json([
                        'date'=> Null,
                        'status'=>200,
                    'message' => 'OTP has been sent to your mobile number, Please check your inbox'
                ], 200);
                    
                }
            } else {
                return response()->json([
                    'message' => 'Failed to send OTP. HTTP Status Code: ' . $httpCode
                ], 500);
            }
        }
        curl_close($ch);
    }

    public function logout(Request $request)
    {
        $user = auth()->user();

        if ($user) {
            // Revoke all active tokens of the user
            $user->tokens()->where('revoked', false)->update(['revoked' => true]);
        }

        return response()->json([
            'message' => 'Logout successful',
            'status'=>200,
        ], 200);
    }
    
    public function deleteAccount(Request $request)
{
    $user = auth()->user();
    if ($user) {
        $user->tokens()->delete();
        $user->delete();
        return response()->json([
            'message' => 'Account deleted successfully.',
            'status' => 200,
        ]);
    }
    return response()->json([
        'message' => 'User not authenticated.',
        'status' => 401,
    ], 401);
}

public function updateProfile(Request $request)
{
    $user = auth()->user(); // Make sure to use auth middleware in route

    $request->validate([
        'first_name' => 'sometimes|required|string',
        'last_name' => 'sometimes|required|string',
        'role' => 'sometimes|required|in:guest,lawyer',
        'mobile_number' => 'sometimes|required|regex:/^(\+92)[0-9]{10}$/',

        // Optional fields
        'country' => 'nullable|string',
        'state' => 'nullable|string',
        'city' => 'nullable|string',

        // Conditional for lawyer
        'license_no' => 'required_if:role,lawyer',
        'consultation_fee' => 'required_if:role,lawyer|numeric',
        'lawyer_practice' => 'required_if:role,lawyer|string',
          'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
    ]);

    // Mobile number uniqueness check
    if ($request->mobile_number && $request->mobile_number !== $user->mobile_number) {
        $mobileExists = User::where('mobile_number', $request->mobile_number)->exists();
        if ($mobileExists) {
            return response()->json([
                'message' => 'Mobile number already exists.'
            ], 422);
        }
    }
   if ($request->hasFile('profile_image')) {
    $image = $request->file('profile_image');
    $imageName = time() . '_' . $image->getClientOriginalName();
    $destinationPath = public_path('uploads/profile_images');

    if (!file_exists($destinationPath)) {
        mkdir($destinationPath, 0755, true);
    }

    if ($user->profile_image && file_exists(public_path($user->profile_image))) {
        unlink(public_path($user->profile_image));
    }
    $image->move($destinationPath, $imageName);
    $user->profile_image = 'uploads/profile_images/' . $imageName;
}


    // Update user fields if provided
    $user->update([
        'first_name' => $request->first_name ?? $user->first_name,
        'last_name' => $request->last_name ?? $user->last_name,
        'mobile_number' => $request->mobile_number ?? $user->mobile_number,
        'role' => $request->role ?? $user->role,
        'country' => $request->country ?? $user->country,
        'state' => $request->state ?? $user->state,
        'city' => $request->city ?? $user->city,
        'license_no' => $request->license_no ?? $user->license_no,
        'consultation_fee' => $request->consultation_fee ?? $user->consultation_fee,
        'lawyer_practice' => $request->lawyer_practice ?? $user->lawyer_practice,
        'profile_image' => $user->profile_image ?? $user->profile_image,
    ]);

    return response()->json([
        'message' => 'User profile updated successfully.',
        'status' => 200,
        'user' => $user
    ]);
}

public function getProfile(Request $request)
{
    $user = auth()->user(); // Logged-in user
  // Convert relative image path to full URL
    if ($user->profile_image) {
        $user->profile_image = asset($user->profile_image);  // Will generate: http://yourdomain.com/uploads/profile_images/filename.jpg
    }
    return response()->json([
         'message' => 'User profile Get successfully.',
        'status' => 200,
        'user' => $user
    ]);
}

}
