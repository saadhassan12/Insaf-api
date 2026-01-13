<?php

use App\Http\Controllers\GuestController;
use App\Http\Controllers\LawyerController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ChatController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});


//user
Route::post('/signup', [UserController::class, 'signup']);
Route::post('/verify-otp', [UserController::class, 'verifyOtp']);
Route::post('/login', [UserController::class, 'login']);
Route::controller(UserController::class)->middleware(['auth:api'])->group(function () {
    Route::post('/updateProfile', 'updateProfile')->name('updateProfile');
    Route::get('/getprofile', 'getprofile')->name('getprofile');

    Route::post('/logout', 'logout')->name('logout');
    Route::get('/deleteAccount','deleteAccount')->name('deleteAccount');
});

Route::controller(GuestController::class)->middleware(['auth:api'])->group(function () {

    Route::prefix('guests')->group(function () {
        Route::post('/add',  'store')->name('add');
      Route::get('by-date', 'getByDate')->name('getByDate');
        Route::get('getLawyers',  'getLawyers')->name('getLawyers');
        Route::get('getrequest',  'getrequest')->name('getrequest');
           Route::post('add/transaction',  'transaction')->name('transaction');
        Route::get('get/transaction/{id}',  'gettransaction')->name('gettransaction');
         Route::post('/case/feedback',  'submitFeedback');
        Route::get('/feedback',  'getUserFeedback');
         Route::post('add/meeting',  'meetingstore')->name('meetingstore');
        Route::get('get/meeting',  'getmeeting')->name('getmeeting');
        Route::post('/update/meetings/{id}', 'meetingupdate');
        Route::get('delete/meeting/{id}',  'deletemeeting')->name('deletemeeting');


    });
});

Route::controller(LawyerController::class)->middleware(['auth:api'])->group(function () {
    Route::prefix('lawyer')->group(function () {
        //case
        Route::get('get',  'getlawyer')->name('getlawyer');
        
         Route::get('getsearch',  'getsearch')->name('getsearch');

        
        Route::get('getbyrequest',  'getbyrequest')->name('getbyrequest');
        Route::post('accept/{id}',  'accept')->name('accpet');
        Route::post('reject/{id}',  'reject')->name('reject');
        Route::get('get/case',  'lawyergetcase')->name('lawyergetcase');
        Route::get('delete/case/{id}',  'deletecase')->name('deletecase');
        Route::post('add/case',  'lawyeraddcase')->name('lawyeraddcase');
        
        Route::post('/case/update/{id}', 'updateCase');

        Route::post('add/proceeding',  'lawyeraddproceeding')->name('lawyeraddproceeding');
        Route::get('/proceeding/{case_id}','getProceedingByCaseId');
        Route::post('/update/proceeding/{case_id}',  'updateProceedingByCaseId');
        
        Route::post('/cases/assign-lawyers/{id}',  'assignLawyersToCase');
        Route::get('get/cases/assign-lawyers/{id}',  'getCaseLawyersWithDetails');
        Route::get('/case/{caseId}/lawyer/{lawyerId}','removeSingleLawyerFromCase');


        //meeting
        Route::post('add/meeting',  'meetingstore')->name('meetingstore');
        Route::get('get/meeting',  'getmeeting')->name('getmeeting');
        Route::get('delete/meeting/{id}',  'deletemeeting')->name('deletemeeting');
        Route::post('add/follow/meeting',  'followmeetingstore')->name('followmeetingstore');
        Route::get('get/follow/meeting',  'followmeetingget')->name('followmeetingget');
        //payment
        Route::post('add/payment',  'paymentstore')->name('paymentstore');
        Route::get('get/payment',  'getpayment')->name('getpayment');
        Route::get('delete/payment/{id}',  'deletepayment')->name('deletepayment');
        // case close
        Route::post('close/case',  'closecase')->name('closecase');
        Route::get('get/close/case',  'getclosecase')->name('getclosecase');
        ///api/lawyer/add/attachment
        Route::post('add/attachment',  'attachment')->name('attachment');
        Route::get('get/attachment/{id}',  'getattachment')->name('getattachment');
        Route::post('/attachments/update/{id}',  'updateAttachment');
        Route::get('/attachments/{id}', 'deleteAttachment');
         //my team 
        Route::post('add/my_team',  'my_team')->name('my_team');
        Route::get('get/my_team',  'getteam')->name('my_team');
        Route::post('/team-member/leave/{id}', 'leaveTeam');
        //Transaction
        Route::post('add/transaction',  'transaction')->name('transaction');
        Route::get('get/transaction/{id}',  'gettransaction')->name('gettransaction');
        Route::post('/transactions/update/{id}', 'updateTransaction');
        Route::get('/transactions/{id}',  'deleteTransaction');
        
        Route::post('/case/feedback',  'submitFeedback');
        Route::get('/feedback',  'getUserFeedback');




    });
});

// Chat Routes - For Lawyers Only
Route::controller(ChatController::class)->middleware(['auth:api'])->group(function () {
    Route::prefix('chat')->group(function () {
        // Group Management
        Route::post('/groups/create', 'createGroup')->name('chat.groups.create');
        Route::get('/groups', 'getGroups')->name('chat.groups.index');
        Route::get('/groups/{groupId}', 'getGroupDetails')->name('chat.groups.show');
        Route::put('/groups/{groupId}', 'updateGroup')->name('chat.groups.update');
        Route::delete('/groups/{groupId}', 'deleteGroup')->name('chat.groups.delete');
        
        // Group Members
        Route::post('/groups/{groupId}/add-members', 'addMembers')->name('chat.groups.addMembers');
        Route::delete('/groups/{groupId}/remove-member/{memberId}', 'removeMember')->name('chat.groups.removeMember');
        Route::post('/groups/{groupId}/leave', 'leaveGroup')->name('chat.groups.leave');
        
        // Messages
        Route::post('/groups/{groupId}/messages', 'sendMessage')->name('chat.messages.send');
        Route::get('/groups/{groupId}/messages', 'getMessages')->name('chat.messages.index');
        Route::delete('/messages/{messageId}', 'deleteMessage')->name('chat.messages.delete');
        Route::post('/groups/{groupId}/mark-read', 'markAsRead')->name('chat.messages.markRead');
        
        // Lawyers List
        Route::get('/lawyers', 'getLawyers')->name('chat.lawyers');
    });
});

use App\Notifications\FirebasePushNotification;

Route::post('/send-notification', function (Request $request) {
    $request->validate([
        'title' => 'required|string',
        'body' => 'required|string',
        
    ]);

    $notification = new FirebasePushNotification(
        $request->title,
        $request->body,
        $request->device_token = 'd3_E1o1uR-aq08j3q_MlZK:APA91bHGN18z33046QLKZGBOM5-G99RrQeKWT5koOBGjwG6dZGKBloPuhQX_04hH7m7DfZ6ew7zdPfNa1Bs4y-gsk43KX0Dw-sSpVNnIzS9L7Skb8P05lKY'
    );

    $response = $notification->toFirebase();

    return response()->json([
        'message' => 'Notification sent!',
        'firebase_response' => $response
    ]);
})->middleware('auth:api');