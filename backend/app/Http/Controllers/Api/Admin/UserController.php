<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class UserController extends ApiController
{
    public function index(Request $request)
    {
        $users = User::query()
            ->with(['driverProfile', 'conciergeProfile', 'wallet'])
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(fn ($inner) => $inner->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return $this->ok(UserResource::collection($users)->response()->getData(true));
    }

    public function show(User $user)
    {
        return $this->ok(new UserResource(
            $user->load(['driverProfile', 'conciergeProfile', 'riderProfile', 'wallet'])
        ));
    }

    public function updateStatus(
        UpdateUserStatusRequest $request,
        User $user,
        AuditLogService $auditLogService,
        NotificationService $notificationService
    ) {
        $data = $request->validated();
        $old = $user->status;

        $user->update([
            'status' => $data['status'],
            'status_reason' => $data['reason'] ?? null,
            'suspended_at' => in_array($data['status'], ['suspended', 'blocked'], true) ? now() : null,
        ]);

        if ($user->isRole('driver')) {
            $profileChanges = $data['status'] === 'active'
                ? ['blocked_at' => null]
                : ['online_status' => false];

            $user->driverProfile?->update($profileChanges);
        }

        $auditLogService->record($request->user(), 'user_status_changed', $user, ['status' => $old], ['status' => $data['status'], 'reason' => $data['reason'] ?? null]);
        $notificationService->push($user, 'account_status', 'Account status updated', "Your account status is now: {$data['status']}.", ['severity' => $data['status'] === 'active' ? 'success' : 'warning']);

        return $this->ok(new UserResource($user->fresh(['driverProfile', 'wallet'])), 'User status updated');
    }
}
