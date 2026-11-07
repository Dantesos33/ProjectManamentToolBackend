<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Cookie;

class AuthTokenService
{
    private const COOKIE = 'refresh_token';
    private const PATH = '/api/auth';   // browser only sends the cookie to /api/auth/*

    /** New login: new family plus its first refresh token. Returns [plainToken, familyId]. */
    public function start(User $user, Request $request): array
    {
        $family = (string) Str::uuid();

        return [$this->issue($user, $family, $request), $family];
    }

    /** Create a refresh token row and return the plain value (only ever shown once). */
    public function issue(User $user, string $family, Request $request): string
    {
        $plain = Str::random(64);

        RefreshToken::create([
            'user_id' => $user->id,
            'family_id' => $family,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDays(config('refresh.ttl_days')),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
        ]);

        return $plain;
    }

    public function accessToken(User $user, string $family): string
    {
        // Old access tokens are not deleted here. They expire in minutes, and deleting
        // one could break a concurrent request that is still using it.
        return $user->createToken(
            "spa:$family",
            ['*'],
            now()->addMinutes(config('refresh.access_ttl_minutes')),
        )->plainTextToken;
    }

    public function revokeFamily(string $family): void
    {
        $userIds = RefreshToken::where('family_id', $family)->pluck('user_id')->unique();

        RefreshToken::where('family_id', $family)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        PersonalAccessToken::where('name', "spa:$family")
            ->whereIn('tokenable_id', $userIds)
            ->delete();
    }

    public function cookie(string $plain): Cookie
    {
        return $this->makeCookie($plain, 60 * 24 * config('refresh.ttl_days'));
    }

    public function forgetCookie(): Cookie
    {
        return $this->makeCookie('', -2628000);   // expires in the past
    }

    private function makeCookie(string $value, int $minutes): Cookie
    {
        return cookie(
            self::COOKIE,
            $value,
            $minutes,
            self::PATH,
            config('refresh.cookie_domain'),
            config('refresh.secure'),
            true,    // httpOnly: JavaScript can never read it
            false,
            config('refresh.same_site'),
        );
    }
}