<?php

namespace App\Http\Controllers;

use App\Models\DocumentFormat;
use App\Models\ForeignTrip;
use App\Support\ModuleAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * «Гадаад улсад зорчих хүсэлт» маягтыг хэвлэх.
 */
class ForeignTripPrintController extends Controller
{
    private const MODULE = 'foreign-trips';

    public function show(Request $request, ForeignTrip $foreignTrip): View
    {
        abort_unless(ModuleAccess::canView($request->user(), self::MODULE), 403);

        $format = DocumentFormat::defaultFormat();

        return view('foreign-trips.print', [
            'trip' => $foreignTrip,
            'format' => [
                'width' => (float) ($format?->width_mm ?? 210),
                'height' => (float) ($format?->height_mm ?? 297),
                'top' => (float) ($format?->margin_top_mm ?? 20),
                'right' => (float) ($format?->margin_right_mm ?? 10),
                'bottom' => (float) ($format?->margin_bottom_mm ?? 20),
                'left' => (float) ($format?->margin_left_mm ?? 30),
                'font' => $format?->font_name ?: 'Arial',
                'size' => (float) ($format?->font_size_pt ?? 12),
                'spacing' => (float) ($format?->line_spacing ?? 1.4),
            ],
        ]);
    }
}
