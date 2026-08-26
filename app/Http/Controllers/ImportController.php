<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImportUploadRequest;
use App\Jobs\ProcessExcelImport;
use App\Models\FreewifiImportBatch;
use App\Services\ImportService;
use Inertia\Inertia;

class ImportController extends Controller
{
    public function index()
    {
        $batches = FreewifiImportBatch::with('importer')->latest()->paginate(20);

        return Inertia::render('Import/Index', ['batches' => $batches]);
    }

    public function upload(StoreImportUploadRequest $request, ImportService $importService)
    {
        $file = $request->file('file');
        $type = $request->input('type', 'sites');

        $path = $file->store('uploads/excel', 'local');
        $batch = $importService->beginImport($file->getClientOriginalName(), (int) auth()->id(), $type);
        ProcessExcelImport::dispatch($batch, storage_path('app/'.$path), $type, auth()->id());

        return redirect()->route('import.show', $batch);
    }

    public function show(FreewifiImportBatch $batch)
    {
        $batch->load('importer');

        return Inertia::render('Import/Show', ['batch' => $batch]);
    }
}
