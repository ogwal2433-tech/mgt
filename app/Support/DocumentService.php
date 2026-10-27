<?php

namespace App\Support;

use App\Models\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class DocumentService
{
    public const MAX_KB = 20480; // 20MB default, configurable via settings (kilobytes)

    /** @var array<string, string> */
    public const ALLOWED_MIME = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'csv' => 'text/csv',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'txt' => 'text/plain',
        'zip' => 'application/zip',
    ];

    public static function store(
        UploadedFile $file,
        string $category = 'general',
        ?Model $linked = null,
        bool $confidential = false,
        ?string $description = null,
    ): Document {
        $maxBytes = (int) (Settings::get('uploads.max_bytes', self::MAX_KB)) * 1024;

        if ($file->getSize() > $maxBytes) {
            throw new RuntimeException('File exceeds the maximum allowed size.');
        }

        $extension = strtolower(self::originalExtension($file));
        if (! array_key_exists($extension, self::ALLOWED_MIME)) {
            throw new RuntimeException('File type is not allowed.');
        }

        $uuid = (string) Str::uuid();
        $safeName = Str::slug(pathinfo(self::originalName($file), PATHINFO_FILENAME)) . '.' . $extension;
        $path = sprintf('documents/%s/%s', $uuid, $safeName);

        Storage::disk('local')->putFileAs(dirname($path), $file, basename($path));

        return Document::create([
            'uuid' => $uuid,
            'name' => $description ?: pathinfo(self::originalName($file), PATHINFO_FILENAME),
            'original_filename' => self::originalName($file),
            'path' => $path,
            'disk' => 'local',
            'mime_type' => $file->getMimeType() ?: self::ALLOWED_MIME[$extension] ?? null,
            'size' => $file->getSize() ?: 0,
            'category' => $category,
            'linked_type' => $linked ? $linked->getMorphClass() : null,
            'linked_id' => $linked?->getKey(),
            'owner_user_id' => Auth::id(),
            'is_confidential' => $confidential,
            'checksum' => self::checksum($file),
            'uploaded_by' => Auth::id(),
        ]);
    }

    private static function originalName(UploadedFile $file): string
    {
        foreach (['getClientOriginalFilename', 'getClientOriginalName'] as $method) {
            if (method_exists($file, $method)) {
                return (string) $file->{$method}();
            }
        }

        return (string) ($file->name ?? 'upload');
    }

    private static function originalExtension(UploadedFile $file): string
    {
        foreach (['getClientOriginalExtension', 'getExtension'] as $method) {
            if (method_exists($file, $method)) {
                $ext = $file->{$method}();
                if ($ext) {
                    return (string) $ext;
                }
            }
        }

        return (string) pathinfo(self::originalName($file), PATHINFO_EXTENSION);
    }

    private static function checksum(UploadedFile $file): ?string
    {
        try {
            $path = $file->getRealPath();

            return $path && is_file($path) ? (hash_file('sha256', $path) ?: null) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Store raw PDF content generated on the server.
     */
    public static function storeGeneratedPdf(
        string $binary,
        string $filename,
        string $category,
        ?Model $linked = null,
        bool $confidential = false,
    ): Document {
        $uuid = (string) Str::uuid();
        $safeName = Str::slug(pathinfo($filename, PATHINFO_FILENAME)) . '.pdf';
        $path = sprintf('documents/%s/%s', $uuid, $safeName);

        Storage::disk('local')->put($path, $binary);

        return Document::create([
            'uuid' => $uuid,
            'name' => pathinfo($filename, PATHINFO_FILENAME),
            'original_filename' => $safeName,
            'path' => $path,
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'size' => strlen($binary),
            'category' => $category,
            'linked_type' => $linked ? $linked->getMorphClass() : null,
            'linked_id' => $linked?->getKey(),
            'owner_user_id' => Auth::id(),
            'is_confidential' => $confidential,
            'checksum' => hash('sha256', $binary),
            'uploaded_by' => Auth::id(),
        ]);
    }

    public static function readable(Document $document): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        if ($document->owner_user_id === $user->id || $document->uploaded_by === $user->id) {
            return true;
        }

        // The person a submission was addressed to can view/download its files.
        if (str_ends_with((string) $document->linked_type, 'DocumentSubmission')) {
            $submission = \App\Models\DocumentSubmission::find($document->linked_id);
            if ($submission && $submission->recipient_id === $user->id) {
                return true;
            }
        }

        if ($document->is_confidential && ! $user->hasPermission(Permissions::DOCUMENTS_DOWNLOAD_CONFIDENTIAL)) {
            return false;
        }

        return $user->hasAnyPermission([
            Permissions::DOCS_REVIEW,
            Permissions::EMPLOYEES_MANAGE,
            Permissions::DOCUMENTS_DOWNLOAD_CONFIDENTIAL,
            Permissions::CONTRACTS_MANAGE,
            Permissions::FINANCE_VIEW,
            Permissions::SYSTEM_USERS,
        ]);
    }

    public static function stream(Document $document, bool $inline = false)
    {
        $headers = [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
        ];

        if (! $inline) {
            return Storage::disk('local')->download($document->path, $document->original_filename, $headers);
        }

        return response()->file(Storage::disk('local')->path($document->path), $headers);
    }
}
