<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\RefreshToken;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private AuthTokenService $tokens)
    {
    }

    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name' => $data['company_name'],
                'domain' => $data['domain'],
            ]);

            return User::create([
                'tenant_id' => $tenant->id,
                'role' => UserRole::Admin,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
        });

        return $this->startSession($user, $request, 201);
    }

    public function login(LoginRequest $request)
    {
        $tenant = Tenant::where('domain', $request->validated('domain'))->first();

        $user = $tenant
            ? User::where('tenant_id', $tenant->id)
                ->where('email', $request->validated('email'))
                ->first()
            : null;

        if (!$user || !Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return $this->startSession($user, $request);
    }

    public function refresh(Request $request)
    {
        $plain = $request->cookie('refresh_token');

        if (!$plain) {
            return $this->unauthenticated();
        }

        return DB::transaction(function () use ($plain, $request) {
            $row = RefreshToken::where('token_hash', hash('sha256', $plain))
                ->lockForUpdate()   // serializes concurrent refreshes using the same token
                ->first();

            if (!$row || $row->revoked_at || $row->expires_at->isPast() || !$row->user) {
                return $this->unauthenticated();
            }

            if ($row->used_at) {
                $withinGrace = $row->used_at->gt(now()->subSeconds(config('refresh.grace_seconds')));

                if ($withinGrace) {
                    // Two refreshes raced (StrictMode, second tab). The browser already holds
                    // the newer cookie from the first response, so only send an access token.
                    return $this->authResponse($row->user, $row->family_id);
                }

                // An old token was replayed outside the grace window: assume it was stolen
                $this->tokens->revokeFamily($row->family_id);

                return $this->unauthenticated();
            }

            $row->update(['used_at' => now()]);
            $newPlain = $this->tokens->issue($row->user, $row->family_id, $request);

            return $this->authResponse($row->user, $row->family_id)
                ->withCookie($this->tokens->cookie($newPlain));
        });
    }

    public function logout(Request $request)
    {
        // Public route on purpose: it must still work when the access token has expired
        if ($plain = $request->cookie('refresh_token')) {
            $row = RefreshToken::where('token_hash', hash('sha256', $plain))->first();

            if ($row) {
                $this->tokens->revokeFamily($row->family_id);
            }
        }

        return response()->json(['message' => 'Logged out successfully.'])
            ->withCookie($this->tokens->forgetCookie());
    }

    public function logoutAll(Request $request)
    {
        $user = $request->user();

        $user->tokens()->delete();
        RefreshToken::where('user_id', $user->id)->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        return response()->json(['message' => 'Logged out from all devices.'])
            ->withCookie($this->tokens->forgetCookie());
    }

    public function me(Request $request)
    {
        return response()->json($this->userData($request->user()));
    }

    private function startSession(User $user, Request $request, int $status = 200): JsonResponse
    {
        [$plain, $family] = $this->tokens->start($user, $request);

        return $this->authResponse($user, $family, $status)
            ->withCookie($this->tokens->cookie($plain));
    }

    private function authResponse(User $user, string $family, int $status = 200): JsonResponse
    {
        return response()->json([
            'token' => $this->tokens->accessToken($user, $family),
            'expires_in' => config('refresh.access_ttl_minutes') * 60,
            'user' => $this->userData($user),
        ], $status);
    }

    private function unauthenticated(): JsonResponse
    {
        return response()->json(['message' => 'Unauthenticated.'], 401)
            ->withCookie($this->tokens->forgetCookie());
    }

    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
        ];
    }
}