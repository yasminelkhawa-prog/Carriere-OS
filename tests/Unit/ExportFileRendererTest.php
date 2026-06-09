<?php

namespace Tests\Unit;

use App\Services\Reporting\ExportFileRenderer;
use PHPUnit\Framework\TestCase;

class ExportFileRendererTest extends TestCase
{
    public function test_render_pdf_outputs_aligned_table_with_truncation_note(): void
    {
        $renderer = new ExportFileRenderer();

        $pdf = $renderer->renderPdf(
            'Candidate List Export',
            ['Application ID', 'Candidate', 'Email'],
            [[
                'a13a0bc2-f6fb-4891-8e0e-947a20d0df94',
                'Point3 NonReferral Candidate 01',
                'pt3.nonref.20260305103254.01.long.address@example.test',
            ]],
            ['filter_snapshot' => ['status' => 'active']]
        );

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('Rows: 1', $pdf);
        $this->assertStringContainsString('Filters: status=active', $pdf);
        $this->assertStringContainsString('| #', $pdf);
        $this->assertStringContainsString('Note: Some values are truncated for readability in PDF.', $pdf);
    }
}
