<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        // One transaction: if the user insert fails, the tenant is rolled back too
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

        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
            ],
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $tenant = Tenant::where('domain', $request->validated('domain'))->first();

        $user = $tenant
            ? User::where('tenant_id', $tenant->id)
                ->where('email', $request->validated('email'))
                ->first()
            : null;

        // Same message for "no such workspace", "no such user" and "wrong password"
        if (!$user || !Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function todo(Request $request)
    {
        return response()->json([
            [
                'id' => 1,
                'title' => 'Todo 1',
                'description' => 'This is a todo',
                'completed' => false,
            ],
            [
                'id' => 2,
                'title' => 'Todo 2',
                'description' => 'This is another todo',
                'completed' => true,
            ]
        ]);
    }
}