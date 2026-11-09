<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\TeamMemberResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        return TeamMemberResource::collection(
            User::where('tenant_id', $request->user()->tenant_id)->orderBy('name')->get()
        );
    }

    public function update(Request $request, int $id)
    {
        $target = $this->findInTenant($request, $id);
        $this->authorize('update', $target);

        $role = UserRole::from($request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
        ])['role']);

        if ($target->is($request->user())) {
            throw ValidationException::withMessages(['role' => ["You can't change your own role. Ask another admin."]]);
        }

        DB::transaction(function () use ($target, $role) {
            $adminCount = $this->lockAdmins($target->tenant_id);
            $target->refresh();

            if ($target->role === UserRole::Admin && $role !== UserRole::Admin && $adminCount <= 1) {
                throw ValidationException::withMessages(['role' => ['A workspace needs at least one admin.']]);
            }

            $target->update(['role' => $role]);
        });

        return new TeamMemberResource($target->refresh());
    }

    public function destroy(Request $request, int $id)
    {
        $actor = $request->user();
        $target = $this->findInTenant($request, $id);
        $this->authorize('delete', $target);

        if ($target->is($actor)) {
            throw ValidationException::withMessages(['user' => ["You can't remove yourself. Ask another admin."]]);
        }

        DB::transaction(function () use ($target, $actor) {
            $adminCount = $this->lockAdmins($target->tenant_id);
            $target->refresh();

            if ($target->role === UserRole::Admin && $adminCount <= 1) {
                throw ValidationException::withMessages(['user' => ['A workspace needs at least one admin.']]);
            }

            // projects.created_by is RESTRICT, so hand their projects to the admin doing the removal
            Project::where('created_by', $target->id)->update(['created_by' => $actor->id]);

            $target->tokens()->delete();   // Sanctum tokens aren't removed by a foreign key
            $target->delete();             // refresh tokens cascade; task assignments become null
        });

        return response()->noContent();
    }

    // User has no tenant scope (it would recurse during login), so always look up by tenant explicitly
    private function findInTenant(Request $request, int $id): User
    {
        return User::where('tenant_id', $request->user()->tenant_id)->findOrFail($id);
    }

    // Locking every admin row makes two admins demoting each other at once run one after the other
    private function lockAdmins(int $tenantId): int
    {
        return User::where('tenant_id', $tenantId)
            ->where('role', UserRole::Admin->value)
            ->lockForUpdate()
            ->pluck('id')
            ->count();
    }
}