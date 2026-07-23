<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Driver\UploadDocumentRequest;
use App\Http\Resources\DriverDocumentResource;
use App\Models\DriverDocument;
use App\Models\DriverDocumentRequest;
use App\Services\DriverVerificationService;
use Illuminate\Http\Request;

class DriverDocumentController extends ApiController
{
    public function index(Request $request, DriverVerificationService $verification)
    {
        $driver = $request->user();

        return $this->ok([
            'checklist' => $verification->checklist($driver),
            'documents' => DriverDocumentResource::collection($verification->documentsFor($driver)),
            'has_all_required' => $verification->hasAllRequiredDocuments($driver),
        ]);
    }

    public function store(UploadDocumentRequest $request)
    {
        $driver = $request->user();
        $data = $request->validated();

        $path = $request->file('file')->store('driver_documents');

        $document = DriverDocument::updateOrCreate(
            ['user_id' => $driver->id, 'type' => $data['type']],
            [
                'file_path' => $path,
                'original_name' => $request->file('file')->getClientOriginalName(),
                'status' => DriverDocument::STATUS_PENDING,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'note' => null,
            ]
        );

        // Mark any open admin request for this document type as fulfilled.
        DriverDocumentRequest::where('user_id', $driver->id)
            ->where('type', $data['type'])
            ->whereNull('fulfilled_at')
            ->update(['fulfilled_at' => now()]);

        // Once every required document is uploaded, move the driver into review.
        if ($driver->driverProfile && $driver->driverProfile->approval_state !== 'approved'
            && app(DriverVerificationService::class)->hasAllRequiredDocuments($driver)) {
            $driver->driverProfile->update(['approval_state' => 'pending']);
        }

        return $this->ok(new DriverDocumentResource($document), 'Document uploaded', 201);
    }
}
