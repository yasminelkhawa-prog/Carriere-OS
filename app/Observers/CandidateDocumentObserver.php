<?php

namespace App\Observers;

use App\Models\CandidateDocument;
use App\Services\Cv\CandidateCvParsingPipeline;
use Illuminate\Support\Facades\Log;
use Throwable;

class CandidateDocumentObserver
{
    public function created(CandidateDocument $document): void
    {
        $this->queueCvParsing($document, 'cv_upload');
    }

    public function updated(CandidateDocument $document): void
    {
        if (! $document->wasChanged(['file_url', 'original_filename', 'mime_type', 'file_size_bytes'])) {
            return;
        }

        $this->queueCvParsing($document, 'cv_update');
    }

    private function queueCvParsing(CandidateDocument $document, string $trigger): void
    {
        if ((string) $document->document_type !== CandidateDocument::TYPE_RESUME) {
            return;
        }

        try {
            app(CandidateCvParsingPipeline::class)->queueForResumeDocument(
                resumeDocument: $document,
                trigger: $trigger
            );
        } catch (Throwable $exception) {
            Log::warning('CV parsing dispatch from candidate document observer failed.', [
                'candidate_document_id' => (string) $document->id,
                'candidate_id' => (string) $document->candidate_id,
                'company_id' => (string) $document->company_id,
                'trigger' => $trigger,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
