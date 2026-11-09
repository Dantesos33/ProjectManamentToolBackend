<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvitationResource;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InvitationController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Invitation::class);

        return InvitationResource::collection(
            Invitation::with('inviter:id,name')->whereNull('accepted_at')->latest('id')->get()
        );
    }

    public function store(Request $request)
    {
        $this->authorize('create', Invitation::class);

        $actor = $request->user();
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->where('tenant_id', $actor->tenant_id)
            ],
            'role' => ['required', Rule::enum(UserRole::class)],
        ], ['email.unique' => 'That person is already a member of this workspace.']);

        $plain = Str::random(64);

        $invitation = DB::transaction(function () use ($actor, $data, $plain) {
            // Re-inviting replaces the pending invite, so only the newest link works
            Invitation::where('email', $data['email'])->whereNull('accepted_at')->delete();

            return Invitation::create([
                'invited_by' => $actor->id,
                'email' => $data['email'],
                'role' => $data['role'],
                'token_hash' => hash('sha256', $plain),
                'expires_at' => now()->addDays(7),
            ]);   // tenant_id is stamped by BelongsToTenant
        });

        $url = rtrim(config('app.frontend_url'), '/') . "/accept-invite?token={$plain}";

        try {
            Mail::to($invitation->email)->send(new InvitationMail($invitation->load(['tenant', 'inviter']), $url));
        } catch (\Throwable $e) {
            report($e);
            $invitation->delete();   // don't leave an invite nobody received

            return response()->json(['message' => 'The email could not be sent. Check the mail settings and try again.'], 503);
        }

        // In local development the mail goes to the log, so also hand the link to the UI
        return (new InvitationResource($invitation))
            ->additional(app()->environment('local') ? ['link' => $url] : []);
    }

    public function destroy(Invitation $invitation)
    {
        $this->authorize('delete', $invitation);

        $invitation->delete();

        return response()->noContent();
    }
}