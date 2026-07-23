<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\ConfirmPayoutRequest;
use App\Http\Requests\Admin\CreatePayoutRequest;
use App\Http\Resources\PayoutResource;
use App\Models\Payout;
use App\Models\User;
use App\Services\PayoutService;
use Illuminate\Http\Request;

class PayoutController extends ApiController
{
    public function __construct(private PayoutService $payoutService) {}

    public function index(Request $request)
    {
        $payouts = Payout::query()
            ->with('driver')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('driver_id'), fn ($q) => $q->where('driver_id', $request->integer('driver_id')))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return $this->ok(PayoutResource::collection($payouts)->response()->getData(true));
    }

    public function store(CreatePayoutRequest $request)
    {
        $data = $request->validated();
        $driver = User::findOrFail($data['driver_id']);

        $payout = $this->payoutService->create(
            $request->user(),
            $driver,
            isset($data['amount']) ? (float) $data['amount'] : null,
            $data['note'] ?? null,
            $data['method'] ?? 'manual'
        );

        return $this->ok(new PayoutResource($payout->load('driver')), 'Payout created', 201);
    }

    public function confirm(ConfirmPayoutRequest $request, Payout $payout)
    {
        $payout = $this->payoutService->confirm($request->user(), $payout, $request->validated()['reference'] ?? null);

        return $this->ok(new PayoutResource($payout->load('driver')), 'Payout confirmed');
    }
}
