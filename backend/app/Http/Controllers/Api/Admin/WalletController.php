<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\AdjustWalletRequest;
use App\Http\Resources\WalletResource;
use App\Models\Wallet;
use App\Services\AuditLogService;
use App\Services\WalletService;
use Illuminate\Http\Request;

class WalletController extends ApiController
{
    public function index(Request $request)
    {
        $wallets = Wallet::query()
            ->with('user')
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->paginate(30)
            ->withQueryString();

        return $this->ok(WalletResource::collection($wallets)->response()->getData(true));
    }

    public function show(Wallet $wallet)
    {
        return $this->ok(new WalletResource(
            $wallet->load(['user', 'ledgerEntries' => fn ($q) => $q->latest()->limit(50)])
        ));
    }

    public function adjust(
        AdjustWalletRequest $request,
        Wallet $wallet,
        WalletService $walletService,
        AuditLogService $auditLogService
    ) {
        $data = $request->validated();
        $pointsDelta = (float) ($data['points_delta'] ?? 0);
        $balanceDelta = (float) ($data['balance_delta'] ?? 0);

        $entry = $walletService->adjust($wallet->user, $pointsDelta, $balanceDelta, $data['reason'], [
            'adjusted_by' => $request->user()->id,
        ]);

        $auditLogService->record($request->user(), 'wallet_adjusted', $wallet, [], [
            'points_delta' => $pointsDelta,
            'balance_delta' => $balanceDelta,
            'reason' => $data['reason'],
        ]);

        return $this->ok([
            'wallet' => new WalletResource($wallet->fresh('user')),
            'ledger_entry' => $entry,
        ], 'Wallet adjusted');
    }
}
