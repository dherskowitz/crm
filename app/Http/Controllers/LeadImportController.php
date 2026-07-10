<?php

namespace App\Http\Controllers;

use App\Jobs\ImportLeads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LeadImportController extends Controller
{
    private const DISK = 'local';

    private const FIELDS = ['name', 'email', 'phone', 'company', 'value'];

    public function index(): Response
    {
        return Inertia::render('leads/import', [
            'fields' => self::FIELDS,
        ]);
    }

    /**
     * Store the uploaded CSV and return its header row for column mapping.
     */
    public function preview(Request $request): Response
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $path = $request->file('file')->store('imports', self::DISK);

        if ($path === false) {
            throw ValidationException::withMessages(['file' => 'The uploaded file could not be stored.']);
        }

        $headers = $this->readHeader($path);

        return Inertia::render('leads/import', [
            'fields' => self::FIELDS,
            'headers' => $headers,
            'storedPath' => $path,
        ]);
    }

    /**
     * Queue the import using the confirmed column mapping.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'starts_with:imports/', 'not_regex:/\.\./'],
            'mapping' => ['required', 'array'],
            'mapping.name' => ['required', 'string'],
            'mapping.email' => ['nullable', 'string'],
            'mapping.phone' => ['nullable', 'string'],
            'mapping.company' => ['nullable', 'string'],
            'mapping.value' => ['nullable', 'string'],
        ]);

        if (! Storage::disk(self::DISK)->exists($validated['path'])) {
            throw ValidationException::withMessages(['path' => 'The uploaded file is no longer available.']);
        }

        ImportLeads::dispatch(self::DISK, $validated['path'], $validated['mapping']);

        return redirect()->route('leads.index')->with('status', 'import-queued');
    }

    /**
     * @return list<string>
     */
    private function readHeader(string $path): array
    {
        $contents = (string) Storage::disk(self::DISK)->get($path);
        $firstLine = strtok(trim($contents), "\r\n") ?: '';

        return array_map(fn ($h) => trim((string) $h), str_getcsv($firstLine, ',', '"', ''));
    }
}
