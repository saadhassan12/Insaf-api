<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use GuzzleHttp\Client;
use Google_Client;

class FirebasePushNotification extends Notification
{
    private $title;
    private $body;
    private $deviceToken;

    public function __construct($title, $body, $deviceToken)
    {
        $this->title = $title;
        $this->body = $body;
        $this->deviceToken = $deviceToken;
    }

    public function via($notifiable)
    {
        return [];
    }

    public function toFirebase()
    {
        $accessToken = $this->getAccessToken();

        if (!$accessToken) {
            return ['error' => 'Access token not generated'];
        }

        $client = new Client();
        $url = "https://fcm.googleapis.com/v1/projects/" . env('FCM_PROJECT_ID') . "/messages:send";

        $response = $client->post($url, [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'message' => [
                    'token' => $this->deviceToken,
                    'notification' => [
                        'title' => $this->title,
                        'body' => $this->body,
                    ],
                    'android' => [
                        'ttl' => '3600s', // 👈 1 hour TTL (required)
                        'priority' => 'high',
                    ],
                    'apns' => [
                        'headers' => [
                            'apns-priority' => '10',
                        ],
                        'payload' => [
                            'aps' => [
                                'sound' => 'default',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

    return response()->json([
        'message' => 'Notification sent!',
        'firebase_response' => json_decode($response->getBody(), true),
        'sent_data' => [
            'title' => $this->title,
            'body' => $this->body,
            'to' => $this->deviceToken,
        ]
    ]);   
    }

    private function getAccessToken()
    {
        $serviceAccountPath = base_path(env('FCM_SERVICE_ACCOUNT_PATH'));

        if (!file_exists($serviceAccountPath)) {
            dd("Firebase credentials file not found at: " . $serviceAccountPath);
        }

        $client = new Google_Client();
        $client->setAuthConfig($serviceAccountPath);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

        $token = $client->fetchAccessTokenWithAssertion();

        if (isset($token['access_token'])) {
            return $token['access_token'];
        }

        return null;
    }
}