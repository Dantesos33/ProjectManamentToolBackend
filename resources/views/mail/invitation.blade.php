<x-mail::message>
    # You're invited

    {{ $invitation->inviter?->name ?? 'An administrator' }} invited you to join **{{ $invitation->tenant->name }}** on
    DeesOps as **{{ ucfirst($invitation->role->value) }}**.

    <x-mail::button :url="$acceptUrl">
        Accept invitation
    </x-mail::button>

    This link expires on {{ $invitation->expires_at->toDayDateTimeString() }}. If you weren't expecting this, you can
    ignore this email.
</x-mail::message>
