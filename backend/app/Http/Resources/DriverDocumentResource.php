<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fileName = (string) ($this->original_name ?: basename((string) $this->file_path));
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
        $isPdf = $extension === 'pdf';

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'type' => $this->type,
            'status' => $this->status,
            'file_path' => $this->file_path,
            'original_name' => $this->original_name,
            'file_name' => $fileName,
            'extension' => $extension,
            'is_image' => $isImage,
            'is_pdf' => $isPdf,
            'admin_file_url' => $request->user()?->isRole('admin')
                ? url("/api/admin/documents/{$this->id}/file")
                : null,
            'note' => $this->note,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
