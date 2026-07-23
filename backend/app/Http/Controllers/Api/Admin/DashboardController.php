<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\DriverDocument;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends ApiController
{
    public function index()
    {
        $liveStatuses = [
            Order::STATUS_SEARCHING,
            Order::STATUS_OFFERED,
            Order::STATUS_ASSIGNED,
            Order::STATUS_ARRIVED,
            Order::STATUS_IN_PROGRESS,
        ];

        return $this->ok([
            'users' => [
                'total' => User::count(),
                'by_role' => User::query()->select('role', DB::raw('count(*) as total'))->groupBy('role')->pluck('total', 'role'),
                'suspended' => User::where('status', 'suspended')->orWhere('status', 'blocked')->count(),
            ],
            'drivers' => [
                'online' => User::where('role', 'driver')->whereHas('driverProfile', fn ($q) => $q->where('online_status', true))->count(),
                'pending_verification' => User::where('role', 'driver')->whereHas('driverProfile', fn ($q) => $q->whereIn('approval_state', ['pending', 'incomplete']))->count(),
                'approved' => User::where('role', 'driver')->whereHas('driverProfile', fn ($q) => $q->where('approval_state', 'approved'))->count(),
            ],
            'orders' => [
                'live' => Order::whereIn('status', $liveStatuses)->count(),
                'completed' => Order::where('status', Order::STATUS_COMPLETED)->count(),
                'cancelled' => Order::where('status', Order::STATUS_CANCELLED)->count(),
                'flagged' => Order::whereNotNull('flagged_at')->count(),
                'by_status' => Order::query()->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status'),
            ],
            'revenue' => [
                'gross' => (float) Transaction::sum('fare'),
                'platform_fees' => (float) Transaction::sum('fee'),
                'driver_net' => (float) Transaction::sum('net'),
            ],
            'documents_pending' => DriverDocument::where('status', DriverDocument::STATUS_PENDING)->count(),
            'payouts_pending' => Payout::where('status', Payout::STATUS_PENDING)->count(),
            'recent_activity' => AuditLogResource::collection(
                AuditLog::with('actor')->latest()->limit(10)->get()
            ),
        ]);
    }
}
