<?php
namespace App\Http\Controllers;
use App\Models\FreewifiImportBatch;
use App\Jobs\ProcessExcelImport;
use Illuminate\Http\Request;
use Inertia\Inertia;
class ImportController extends Controller
{
    public function index()
    {
        $batches = FreewifiImportBatch::with('importer')->latest()->paginate(20);
        return Inertia::render('Import/Index', ['batches' => $batches]);
    }
    public function upload(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);
        $file = $request->file('file');
        $path = $file->store('uploads/excel', 'local');
        $batch = app(\App\Services\ImportService::class)->beginImport($file->getClientOriginalName(), auth()->id());
        ProcessExcelImport::dispatch($batch, storage_path('app/' . $path));
        return redirect()->route('import.show', $batch);
    }
    public function show(FreewifiImportBatch $batch)
    {
        $batch->load('importer');
        return Inertia::render('Import/Show', ['batch' => $batch]);
    }
}
