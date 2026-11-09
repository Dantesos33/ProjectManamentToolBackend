<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvitationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role->value,
            'expired' => $this->expires_at->isPast(),
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
            'invited_by' => $this->whenLoaded('inviter', fn() => $this->inviter
                ? ['id' => $this->inviter->id, 'name' => $this->inviter->name]
                : null),
        ];
    }
}
