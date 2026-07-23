<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers push notifications through Expo's push service
 * (https://docs.expo.dev/push-notifications/sending-notifications/).
 *
 * All delivery is best-effort: a transport failure must never break the
 * request that produced the in-app notification, so every path is wrapped and
 * logged rather than thrown.
 */
class ExpoPushService
{
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    public function enabled(): bool
    {
        return (bool) config('services.expo_push.enabled', true);
    }

    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        if (! $this->enabled()) {
            return;
        }

        $tokens = $user->deviceTokens()
            ->pluck('token')
            ->filter(fn ($token) => $this->isExpoToken($token))
            ->values()
            ->all();

        $this->send($tokens, $title, $body, $data);
    }

    /**
     * @param  array<int, string>  $tokens
     */
    public function send(array $tokens, string $title, string $body, array $data = []): void
    {
        if (! $this->enabled() || $tokens === []) {
            return;
        }

        $messages = array_map(fn (string $token) => [
            'to' => $token,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'sound' => 'default',
            'priority' => 'high',
        ], $tokens);

        try {
            $response = Http::acceptJson()
                ->timeout(5)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post(self::ENDPOINT, $messages);

            $this->pruneInvalidTokens($tokens, $response->json('data'));
        } catch (Throwable $exception) {
            Log::warning('Expo push delivery failed', ['error' => $exception->getMessage()]);
        }
    }

    private function isExpoToken(string $token): bool
    {
        return str_starts_with($token, 'ExponentPushToken[') || str_starts_with($token, 'ExpoPushToken[');
    }

    /**
     * Remove tokens Expo reports as unregistered so they stop receiving pushes.
     *
     * @param  array<int, string>  $tokens
     * @param  mixed  $receipts
     */
    private function pruneInvalidTokens(array $tokens, $receipts): void
    {
        if (! is_array($receipts)) {
            return;
        }

        $dead = [];
        foreach ($receipts as $index => $receipt) {
            $error = is_array($receipt) ? ($receipt['details']['error'] ?? null) : null;
            if (($receipt['status'] ?? null) === 'error' && $error === 'DeviceNotRegistered' && isset($tokens[$index])) {
                $dead[] = $tokens[$index];
            }
        }

        if ($dead !== []) {
            DeviceToken::whereIn('token', $dead)->delete();
        }
    }
}
