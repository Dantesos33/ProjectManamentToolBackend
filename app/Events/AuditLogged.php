<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class AuditLogged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $tenantId, public int $auditId)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->tenantId}.admin")];
    }

    public function broadcastAs(): string
    {
        return 'audit.logged';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->auditId];   // no content: the page refetches through the policy-protected API
    }
}