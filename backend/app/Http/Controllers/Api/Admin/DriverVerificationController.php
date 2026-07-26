<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\RejectDriverRequest;
use App\Http\Requests\Admin\RequestDocumentRequest;
use App\Http\Resources\DriverDocumentResource;
use App\Models\DriverDocument;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\DriverVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DriverVerificationController extends ApiController
{
    public function __construct(private DriverVerificationService $verification) {}

    /**
     * List drivers, optionally filtered by approval_state (pending/approved/rejected/incomplete).
     */
    public function index(Request $request)
    {
        $drivers = User::query()
            ->where('role', 'driver')
            ->with(['driverProfile', 'wallet'])
            ->when($request->filled('approval_state'), function ($q) use ($request) {
                $q->whereHas('driverProfile', fn ($inner) => $inner->where('approval_state', $request->string('approval_state')));
            })
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return $this->ok(UserResource::collection($drivers)->response()->getData(true));
    }

    public function documents(User $driver)
    {
        $this->assertDriver($driver);

        return $this->ok([
            'driver' => new UserResource($driver->load('driverProfile')),
            'checklist' => $this->verification->checklist($driver),
            'documents' => DriverDocumentResource::collection($this->verification->documentsFor($driver)),
            'has_all_required' => $this->verification->hasAllRequiredDocuments($driver),
        ]);
    }

    public function approve(Request $request, User $driver)
    {
        $this->assertDriver($driver);
        $driver = $this->verification->approveDriver($request->user(), $driver);

        return $this->ok(new UserResource($driver->load('driverProfile')), 'Driver approved');
    }

    public function reject(RejectDriverRequest $request, User $driver)
    {
        $this->assertDriver($driver);
        $driver = $this->verification->rejectDriver($request->user(), $driver, $request->validated()['reason']);

        return $this->ok(new UserResource($driver->load('driverProfile')), 'Driver rejected');
    }

    public function requestDocument(RequestDocumentRequest $request, User $driver)
    {
        $this->assertDriver($driver);
        $data = $request->validated();
        $documentRequest = $this->verification->requestDocument($request->user(), $driver, $data['type'], $data['note'] ?? null);

        return $this->ok($documentRequest, 'Document requested', 201);
    }

    public function file(DriverDocument $document): StreamedResponse
    {
        $disk = $this->documentDisk($document);

        abort_unless($disk, 404, 'Document file not found.');

        $name = $document->original_name ?: basename($document->file_path);
        $mime = Storage::disk($disk)->mimeType($document->file_path) ?: 'application/octet-stream';

        return Storage::disk($disk)->response($document->file_path, $name, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$name.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function reviewDocument(Request $request, DriverDocument $document)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                DriverDocument::STATUS_PENDING,
                DriverDocument::STATUS_APPROVED,
                DriverDocument::STATUS_REJECTED,
            ])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $document = $this->verification->reviewDocument(
            $request->user(),
            $document,
            $data['status'],
            $data['note'] ?? null
        );

        return $this->ok(new DriverDocumentResource($document), 'Document reviewed');
    }

    private function assertDriver(User $driver): void
    {
        abort_unless($driver->isRole('driver'), 404, 'Driver not found.');
    }

    private function documentDisk(DriverDocument $document): ?string
    {
        foreach ([config('filesystems.default'), 'local', 'public'] as $disk) {
            if ($disk && Storage::disk($disk)->exists($document->file_path)) {
                return $disk;
            }
        }

        return null;
    }
}
