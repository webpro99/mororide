<?php

namespace App\Services;

use App\Models\DriverDocument;
use App\Models\DriverDocumentRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DriverVerificationService
{
    public function __construct(
        private AuditLogService $auditLogService,
        private NotificationService $notificationService
    ) {}

    /**
     * @return Collection<int, DriverDocument>
     */
    public function documentsFor(User $driver): Collection
    {
        return DriverDocument::where('user_id', $driver->id)->latest('updated_at')->get();
    }

    /**
     * A per-type checklist merging uploaded documents with the required set,
     * so the admin sees what is uploaded, pending, or still missing.
     *
     * @return array<int, array<string, mixed>>
     */
    public function checklist(User $driver): array
    {
        $documents = $this->documentsFor($driver)->keyBy('type');

        return collect(DriverDocument::TYPES)->map(fn (string $type) => [
            'type' => $type,
            'status' => $documents[$type]->status ?? 'missing',
            'document' => $documents[$type] ?? null,
        ])->all();
    }

    public function hasAllRequiredDocuments(User $driver): bool
    {
        $uploaded = DriverDocument::where('user_id', $driver->id)->pluck('type')->all();

        return empty(array_diff(DriverDocument::TYPES, $uploaded));
    }

    public function approveDriver(User $admin, User $driver): User
    {
        $this->ensureDriver($driver);

        return DB::transaction(function () use ($admin, $driver) {
            $old = $driver->driverProfile?->approval_state;

            $driver->driverProfile?->update(['approval_state' => 'approved']);

            DriverDocument::where('user_id', $driver->id)
                ->where('status', DriverDocument::STATUS_PENDING)
                ->update([
                    'status' => DriverDocument::STATUS_APPROVED,
                    'reviewed_by' => $admin->id,
                    'reviewed_at' => now(),
                ]);

            $this->auditLogService->record($admin, 'driver_approved', $driver, ['approval_state' => $old], ['approval_state' => 'approved']);
            $this->notificationService->push($driver, 'driver_approved', 'Driver profile approved', 'Your documents are approved. You can now go online and receive ride requests.', ['screen' => 'driver']);

            return $driver->fresh('driverProfile');
        });
    }

    public function rejectDriver(User $admin, User $driver, string $reason): User
    {
        $this->ensureDriver($driver);

        return DB::transaction(function () use ($admin, $driver, $reason) {
            $old = $driver->driverProfile?->approval_state;

            $driver->driverProfile?->update([
                'approval_state' => 'rejected',
                'online_status' => false,
            ]);

            $this->auditLogService->record($admin, 'driver_rejected', $driver, ['approval_state' => $old], ['approval_state' => 'rejected', 'reason' => $reason]);
            $this->notificationService->push($driver, 'driver_rejected', 'Driver profile rejected', $reason, ['screen' => 'driver', 'severity' => 'warning']);

            return $driver->fresh('driverProfile');
        });
    }

    public function requestDocument(User $admin, User $driver, string $type, ?string $note = null): DriverDocumentRequest
    {
        $this->ensureDriver($driver);

        return DB::transaction(function () use ($admin, $driver, $type, $note) {
            $driver->driverProfile?->update(['approval_state' => 'pending']);

            $request = DriverDocumentRequest::create([
                'user_id' => $driver->id,
                'type' => $type,
                'requested_by' => $admin->id,
                'note' => $note,
            ]);

            $this->auditLogService->record($admin, 'driver_document_requested', $driver, [], ['type' => $type, 'note' => $note]);
            $this->notificationService->push($driver, 'document_requested', 'Document requested', "Please upload your {$type} document.".($note ? " Note: {$note}" : ''), ['screen' => 'verification', 'type' => $type]);

            return $request;
        });
    }

    public function reviewDocument(User $admin, DriverDocument $document, string $status, ?string $note = null): DriverDocument
    {
        $document->update([
            'status' => $status,
            'note' => $note,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $this->auditLogService->record($admin, 'driver_document_reviewed', $document, [], ['type' => $document->type, 'status' => $status]);

        $label = str_replace('_', ' ', $document->type);
        if ($status === DriverDocument::STATUS_APPROVED) {
            $this->notificationService->push(
                $document->user,
                'driver_document_approved',
                'Document approved',
                "Your {$label} document has been approved.",
                ['screen' => 'verification', 'type' => $document->type, 'severity' => 'success']
            );
        }

        if ($status === DriverDocument::STATUS_REJECTED) {
            $this->notificationService->push(
                $document->user,
                'driver_document_rejected',
                'Document rejected',
                "Your {$label} document was rejected.".($note ? " Admin note: {$note}" : ''),
                ['screen' => 'verification', 'type' => $document->type, 'severity' => 'warning']
            );
        }

        return $document->fresh();
    }

    private function ensureDriver(User $driver): void
    {
        if (! $driver->isRole('driver')) {
            throw new RuntimeException('This user is not a driver.');
        }
    }
}
