<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Minimal LiveKit integration: HS256 access tokens + RoomService (Twirp) calls.
 *
 * No external SDK required — tokens are standard LiveKit JWTs and the room
 * admin endpoints are plain authenticated JSON posts against the LiveKit host.
 */
final class LiveKitService
{
    /** Max token lifetime we ever mint. */
    private const MAX_TTL = 86400;

    public static function isConfigured(): bool
    {
        return (bool) (config('services.livekit.url') && config('services.livekit.key') && config('services.livekit.secret'));
    }

    /**
     * Mint a join token for a participant.
     *
     * @param bool $moderator host powers: remove others, end room, room admin
     */
    public static function joinToken(
        string $room,
        string $identity,
        string $displayName,
        bool $moderator = false,
        ?int $ttlSeconds = null,
    ): string {
        $ttl = max(60, min($ttlSeconds ?? 3600, self::MAX_TTL));
        $now = time();

        $video = [
            'room' => $room,
            'roomJoin' => true,
            'canPublish' => true,
            'canSubscribe' => true,
            'canPublishData' => true,
            'canUpdateOwnMetadata' => true,
        ];

        if ($moderator) {
            $video['roomAdmin'] = true;
            $video['canRemoveOthers'] = true;
            $video['canEndRoom'] = true;
        }

        return self::jwt([
            'exp' => $now + $ttl,
            'nbf' => $now - 10,
            'iss' => config('services.livekit.key'),
            'sub' => $identity,
            'name' => $displayName,
            'video' => $video,
        ]);
    }

    /** Kick a participant out of a room. */
    public static function removeParticipant(string $room, string $identity): void
    {
        self::twirp('livekit.RoomService/RemoveParticipant', [
            'room' => $room,
            'identity' => $identity,
        ]);
    }

    /** End a meeting for everyone: deletes the LiveKit room. */
    public static function deleteRoom(string $room): void
    {
        self::twirp('livekit.RoomService/DeleteRoom', ['room' => $room]);
    }

    /** @return array<int, array<string, mixed>> */
    public static function listParticipants(string $room): array
    {
        $response = self::twirp('livekit.RoomService/ListParticipants', ['room' => $room]);

        return $response['participants'] ?? [];
    }

    /* ------------------------------------------------------------------
     * Internals
     * ------------------------------------------------------------------ */

    private static function twirp(string $method, array $payload): array
    {
        $base = self::restBase();

        $response = Http::withToken(self::serverToken())
            ->asJson()
            ->timeout(15)
            ->post("{$base}/twirp/{$method}", $payload);

        if ($response->failed()) {
            throw new \RuntimeException("LiveKit {$method} failed ({$response->status()}): " . substr($response->body(), 0, 300));
        }

        return $response->json() ?? [];
    }

    private static function restBase(): string
    {
        $url = (string) config('services.livekit.url');

        return rtrim(preg_replace('/^wss:/', 'https:', preg_replace('/^ws:/', 'http:', $url)), '/');
    }

    /** Server-side token with room admin rights over all rooms. */
    private static function serverToken(): string
    {
        $now = time();

        return self::jwt([
            'exp' => $now + 300,
            'nbf' => $now - 10,
            'iss' => config('services.livekit.key'),
            'sub' => 'server',
            'video' => [
                'roomAdmin' => true,
                'roomCreate' => true,
                'canRemoveOthers' => true,
                'canEndRoom' => true,
            ],
        ]);
    }

    private static function jwt(array $claims): string
    {
        $header = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = self::b64(json_encode($claims));
        $signature = self::b64(
            hash_hmac('sha256', "{$header}.{$payload}", (string) config('services.livekit.secret'), true)
        );

        return "{$header}.{$payload}.{$signature}";
    }

    private static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
