<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $members = User::where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        return response()->json([
            'data' => $members->map(fn(User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->role->value,
            ]),
        ]);
    }
}
