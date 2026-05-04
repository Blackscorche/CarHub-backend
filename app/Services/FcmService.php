<?php

namespace App\Services;

use App\Models\User;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Illuminate\Support\Facades\Log;

class FcmService
{
    protected $messaging;

    public function __construct()
    {
        $credentialsPath = base_path(env('FIREBASE_CREDENTIALS', 'storage/firebase-service-account.json'));

        if (file_exists($credentialsPath)) {
            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $this->messaging = $factory->createMessaging();
        } else {
            Log::warning('Firebase credentials file not found', ['path' => $credentialsPath]);
        }
    }

    public function sendToUser(User $user, string $title, string $body, array $data = []): bool
    {
        if (!$this->messaging || !$user->fcm_token) {
            return false;
        }

        try {
            $message = CloudMessage::new()
                ->withToken($user->fcm_token)
                ->withNotification(Notification::create($title, $body))
                ->withData($this->flattenData($data));

            $this->messaging->send($message);

            Log::info('FCM sent', ['user_id' => $user->id, 'title' => $title]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('FCM failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            // Invalid token — clear it
            if (str_contains($e->getMessage(), 'not-found') || str_contains($e->getMessage(), 'invalid-registration')) {
                $user->update(['fcm_token' => null]);
            }

            return false;
        }
    }

    public function sendToMultiple(array $tokens, string $title, string $body, array $data = []): void
    {
        if (!$this->messaging || empty($tokens)) return;

        try {
            $message = CloudMessage::new()
                ->withNotification(Notification::create($title, $body))
                ->withData($this->flattenData($data));

            $this->messaging->sendMulticast($message, $tokens);
        } catch (\Throwable $e) {
            Log::warning('FCM multicast failed', ['error' => $e->getMessage()]);
        }
    }

    protected function flattenData(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $result[$key] = json_encode($value);
            } elseif (is_null($value)) {
                $result[$key] = '';
            } else {
                $result[$key] = (string) $value;
            }
        }
        return $result;
    }
}
