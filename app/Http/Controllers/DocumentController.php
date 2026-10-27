<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Support\AuditLogger;
use App\Support\DocumentService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function download(Document $document): StreamedResponse
    {
        if (! DocumentService::readable($document)) {
            abort(403, 'You are not authorised to download this document.');
        }

        AuditLogger::log('document.downloaded', Document::class, $document->id, "Downloaded {$document->original_filename}");

        return DocumentService::stream($document);
    }

    public function preview(Document $document): \Symfony\Component\HttpFoundation\Response
    {
        if (! DocumentService::readable($document)) {
            abort(403, 'You are not authorised to preview this document.');
        }

        AuditLogger::log('document.previewed', Document::class, $document->id, "Previewed {$document->original_filename}");

        return DocumentService::stream($document, true);
    }
}
