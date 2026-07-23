<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends ApiController
{
    public function index(Request $request)
    {
        $transactions = Transaction::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('driver_id'), fn ($q) => $q->where('driver_id', $request->integer('driver_id')))
            ->when($request->filled('city_id'), fn ($q) => $q->where('city_id', $request->integer('city_id')))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return $this->ok(TransactionResource::collection($transactions)->response()->getData(true));
    }

    public function revenue()
    {
        return $this->ok([
            'gross' => (float) Transaction::sum('fare'),
            'fees' => (float) Transaction::sum('fee'),
            'driver_net' => (float) Transaction::sum('net'),
            'transactions' => Transaction::count(),
            'cash_rides' => Transaction::where('type', 'cash')->count(),
            'card_rides' => Transaction::where('type', 'card')->count(),
        ]);
    }
}
