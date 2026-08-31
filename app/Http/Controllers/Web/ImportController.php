<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreImportRequest;
use App\Models\Import;
use App\Services\ImportService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    private const FORMATS = ['txt', 'csv', 'json', 'html'];

    public function __construct(private ImportService $service) {}

    public function index(): Response
    {
        $latest = request()->user()->imports()->latest('id')->first();

        return Inertia::render('Import/Index', [
            'activeImport' => $this->present($latest),
            'formats' => self::FORMATS,
        ]);
    }

    public function store(StoreImportRequest $request): RedirectResponse
    {
        $import = $this->service->start(
            $request->user(),
            $request->file('file'),
            $request->input('format'),
        );

        return redirect()->route('import.show', $import);
    }

    public function show(Import $import): Response
    {
        $this->authorize('view', $import);

        return Inertia::render('Import/Index', [
            'activeImport' => $this->present($import),
            'formats' => self::FORMATS,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function present(?Import $import): ?array
    {
        if ($import === null) {
            return null;
        }

        return [
            'id' => $import->id,
            'format' => $import->format,
            'status' => $import->status,
            'total_rows' => $import->total_rows,
            'processed_rows' => $import->processed_rows,
            'created_count' => $import->created_count,
            'duplicate_count' => $import->duplicate_count,
            'error_count' => $import->error_count,
            'errors' => $import->errors ?? [],
            'original_filename' => $import->original_filename,
            'created_at' => $import->created_at->toIso8601String(),
        ];
    }
}
