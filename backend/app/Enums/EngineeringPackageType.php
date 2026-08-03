<?php

namespace App\Enums;

/**
 * Kind of engineering representation a document holds (#179). One revision may
 * have several representations (a native eDrawings file, a neutral STEP, a PDF
 * drawing and an interactive HTML package).
 */
enum EngineeringPackageType: string
{
    case NativeCad = 'native_cad';
    case NeutralCad = 'neutral_cad';
    case EdrawingsNative = 'edrawings_native';
    case InteractiveHtml = 'interactive_html';
    case Pdf = 'pdf';
    case Image = 'image';

    public function label(): string
    {
        return match ($this) {
            self::NativeCad => __('Native CAD'),
            self::NeutralCad => __('Neutral CAD'),
            self::EdrawingsNative => __('eDrawings'),
            self::InteractiveHtml => __('Interactive viewer'),
            self::Pdf => __('Drawing (PDF)'),
            self::Image => __('Image'),
        };
    }

    /** Rendered inline in the browser (pdf/image); everything else is download-only. */
    public function isInlineViewable(): bool
    {
        return in_array($this, [self::Pdf, self::Image], true);
    }

    /** Active content served through the sandboxed viewer (Phase 3), never inline in the app DOM. */
    public function isInteractive(): bool
    {
        return $this === self::InteractiveHtml;
    }
}
