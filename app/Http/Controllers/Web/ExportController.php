<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __invoke(Request $request, ExportService $service): StreamedResponse
    {
        $format = $request->validate([
            'format' => ['required', 'in:html,csv,json'],
        ])['format'];

        return $service->stream($request->user(), $format);
    }
}
