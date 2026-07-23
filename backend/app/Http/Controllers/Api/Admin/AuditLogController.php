<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends ApiController
{
    public function index(Request $request)
    {
        $logs = AuditLog::query()
            ->with('actor')
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('actor_id'), fn ($q) => $q->where('actor_id', $request->integer('actor_id')))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return $this->ok(AuditLogResource::collection($logs)->response()->getData(true));
    }
}
