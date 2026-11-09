<?php

use App\Mail\InvitationMail;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\{deleteJson, getJson, patchJson, postJson};

function teamUser(string $role = 'developer', ?Tenant $tenant = null): User
{
    return User::factory()->create([
        'tenant_id' => ($tenant ?? Tenant::factory()->create())->id,
        'role' => $role,
    ]);
}

function inviteToken(User $admin, string $email, string $role = 'developer'): string
{
    Mail::fake();
    Sanctum::actingAs($admin);
    postJson('/api/invitations', ['email' => $email, 'role' => $role])->assertCreated();

    $url = '';
    Mail::assertSent(InvitationMail::class, function (InvitationMail $mail) use (&$url) {
        $mail->assertSeeInHtml('Accept invitation', false);   // renders the template, so a broken view fails here
        $url = $mail->acceptUrl;

        return true;
    });

    app('auth')->forgetGuards();   // the accept endpoint is public, so continue as a guest
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    return $query['token'];
}

function acceptPayload(string $token): array
{
    return ['token' => $token, 'name' => 'New Person', 'password' => 'secret1234', 'password_confirmation' => 'secret1234'];
}

it('lets only admins manage invitations and members', function () {
    foreach (['viewer', 'developer', 'manager'] as $role) {
        Sanctum::actingAs(teamUser($role));
        postJson('/api/invitations', ['email' => 'x@y.test', 'role' => 'viewer'])->assertForbidden();
        getJson('/api/invitations')->assertForbidden();
        getJson('/api/users')->assertForbidden();
    }

    Sanctum::actingAs(teamUser('admin'));
    getJson('/api/invitations')->assertOk();
    getJson('/api/users')->assertOk();
});

it('creates the user in the inviting tenant with the invited role', function () {
    $admin = teamUser('admin');
    $token = inviteToken($admin, 'new@acme.test', 'manager');

    // role, email and tenant sent by the invitee are ignored
    postJson('/api/auth/accept-invite', [...acceptPayload($token), 'role' => 'admin', 'email' => 'evil@x.test', 'tenant_id' => 999])
        ->assertCreated()
        ->assertJsonPath('user.role', 'manager')
        ->assertJsonPath('user.email', 'new@acme.test')
        ->assertJsonPath('user.tenant_id', $admin->tenant_id);
});

it('only accepts an invitation once', function () {
    $token = inviteToken(teamUser('admin'), 'new@acme.test');

    postJson('/api/auth/accept-invite', acceptPayload($token))->assertCreated();
    postJson('/api/auth/accept-invite', acceptPayload($token))
        ->assertUnprocessable()->assertJsonValidationErrors('token');
});

it('rejects an expired invitation', function () {
    $token = inviteToken(teamUser('admin'), 'new@acme.test');

    $this->travel(8)->days();

    postJson('/api/auth/accept-invite', acceptPayload($token))->assertUnprocessable();
    postJson('/api/auth/invitations/preview', ['token' => $token])->assertNotFound();
});

it('previews a valid invitation and rejects a made-up one', function () {
    $admin = teamUser('admin');
    $token = inviteToken($admin, 'new@acme.test', 'viewer');

    postJson('/api/auth/invitations/preview', ['token' => $token])
        ->assertOk()
        ->assertJsonPath('data.email', 'new@acme.test')
        ->assertJsonPath('data.role', 'viewer')
        ->assertJsonPath('data.company', $admin->tenant->name);

    postJson('/api/auth/invitations/preview', ['token' => 'nope'])->assertNotFound();
});

it('replaces a pending invitation when the same email is invited again', function () {
    $admin = teamUser('admin');
    $first = inviteToken($admin, 'new@acme.test');
    $second = inviteToken($admin, 'new@acme.test', 'viewer');

    expect(Invitation::withoutGlobalScopes()->count())->toBe(1);

    postJson('/api/auth/accept-invite', acceptPayload($first))->assertUnprocessable();
    postJson('/api/auth/accept-invite', acceptPayload($second))->assertCreated()->assertJsonPath('user.role', 'viewer');
});

it('does not invite someone who is already a member', function () {
    $admin = teamUser('admin');
    $member = teamUser('developer', $admin->tenant);

    Sanctum::actingAs($admin);
    postJson('/api/invitations', ['email' => $member->email, 'role' => 'viewer'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('never lists or touches another tenants members', function () {
    $adminA = teamUser('admin');
    $devB = teamUser('developer');   // different tenant

    Sanctum::actingAs($adminA);

    getJson('/api/users')->assertOk()->assertJsonCount(1, 'data');
    patchJson("/api/users/{$devB->id}", ['role' => 'admin'])->assertNotFound();
    deleteJson("/api/users/{$devB->id}")->assertNotFound();
    expect($devB->fresh()->role->value)->toBe('developer');
});

it('changes a role, applies it immediately and records it', function () {
    $admin = teamUser('admin');
    $dev = teamUser('developer', $admin->tenant);

    Sanctum::actingAs($dev);
    postJson('/api/projects', ['name' => 'P'])->assertForbidden();

    Sanctum::actingAs($admin);
    patchJson("/api/users/{$dev->id}", ['role' => 'manager'])->assertOk()->assertJsonPath('data.role', 'manager');

    Sanctum::actingAs($dev->fresh());
    postJson('/api/projects', ['name' => 'P'])->assertCreated();

    $log = AuditLog::where('action', 'user.updated')->firstOrFail();
    expect($log->old_values)->toBe(['role' => 'developer'])
        ->and($log->new_values)->toBe(['role' => 'manager']);
});

it('stops admins from changing or removing themselves', function () {
    $admin = teamUser('admin');
    Sanctum::actingAs($admin);

    patchJson("/api/users/{$admin->id}", ['role' => 'viewer'])->assertUnprocessable();
    deleteJson("/api/users/{$admin->id}")->assertUnprocessable();
    expect($admin->fresh()->role->value)->toBe('admin');
});

it('removes a user, revokes their tokens and reassigns their projects', function () {
    $admin = teamUser('admin');
    $manager = teamUser('manager', $admin->tenant);
    $project = Project::factory()->create(['tenant_id' => $admin->tenant_id, 'created_by' => $manager->id]);
    $manager->createToken('spa');

    Sanctum::actingAs($admin);
    deleteJson("/api/users/{$manager->id}")->assertNoContent();

    expect(User::find($manager->id))->toBeNull()
        ->and(PersonalAccessToken::count())->toBe(0)
        ->and($project->fresh()->created_by)->toBe($admin->id);
});

it('lets an admin revoke a pending invitation', function () {
    $admin = teamUser('admin');
    $token = inviteToken($admin, 'new@acme.test');

    Sanctum::actingAs($admin);
    $id = getJson('/api/invitations')->assertOk()->json('data.0.id');
    deleteJson("/api/invitations/{$id}")->assertNoContent();

    postJson('/api/auth/accept-invite', acceptPayload($token))->assertUnprocessable();
});