<?php

namespace App\Support;

use App\Models\Document;
use Illuminate\Support\Str;

final class Signature
{
    /**
     * Store an inline signature (PNG data URL) as a private document.
     */
    public static function storeDataUrl(string $dataUrl, string $label, ?int $ownerUserId = null): Document
    {
        if (! preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=]+)$/', $dataUrl, $matches)) {
            throw new \InvalidArgumentException('The signature must be a PNG data URL.');
        }

        $binary = base64_decode($matches[1], true);

        if ($binary === false || strlen($binary) < 100) {
            throw new \InvalidArgumentException('The signature image could not be decoded.');
        }

        $uuid = (string) Str::uuid();
        $name = 'signature-' . Str::slug($label) . '.png';
        $path = sprintf('documents/%s/%s', $uuid, $name);

        \Illuminate\Support\Facades\Storage::disk('local')->put($path, $binary);

        return Document::create([
            'uuid' => $uuid,
            'name' => 'Digital signature — ' . $label,
            'original_filename' => $name,
            'path' => $path,
            'disk' => 'local',
            'mime_type' => 'image/png',
            'size' => strlen($binary),
            'category' => 'signature',
            'owner_user_id' => $ownerUserId,
            'is_confidential' => true,
            'checksum' => hash('sha256', $binary),
            'uploaded_by' => $ownerUserId,
        ]);
    }
}
