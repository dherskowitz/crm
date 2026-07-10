<?php

namespace App\Actions\Leads;

use App\Enums\Source;
use App\Enums\Stage;
use App\Models\Company;
use App\Models\Lead;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportLeadsFromCsv
{
    /**
     * Import leads from a stored CSV using a column mapping, running the same
     * dedupe so it never creates duplicates against existing leads or within
     * the file itself.
     *
     * @param  array<string, string|null>  $mapping  field => CSV header
     * @return array{created: int, skipped: int}
     */
    public function __invoke(string $disk, string $path, array $mapping): array
    {
        $rows = $this->readRows($disk, $path);
        $header = array_shift($rows);

        if ($header === null) {
            return ['created' => 0, 'skipped' => 0];
        }

        $index = $this->columnIndexes($header, $mapping);
        $seenEmails = [];
        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $name = $this->value($row, $index, 'name');
            $email = Str::lower(trim((string) $this->value($row, $index, 'email')));

            if ($name === null && $email === '') {
                $skipped++;

                continue;
            }

            if ($email !== '' && (isset($seenEmails[$email]) || Lead::query()->where('email', $email)->exists())) {
                $skipped++;

                continue;
            }

            Lead::create([
                'name' => $name ?? $email,
                'email' => $email !== '' ? $email : null,
                'phone' => $this->value($row, $index, 'phone'),
                'value' => $this->numeric($this->value($row, $index, 'value')),
                'company_id' => $this->resolveCompany($this->value($row, $index, 'company'))?->id,
                'source' => Source::Import,
                'stage' => Stage::New,
                'last_activity_at' => now(),
            ]);

            if ($email !== '') {
                $seenEmails[$email] = true;
            }

            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * @return list<list<string>>
     */
    private function readRows(string $disk, string $path): array
    {
        $contents = Storage::disk($disk)->get($path);
        $rows = [];

        foreach (preg_split('/\r\n|\r|\n/', trim((string) $contents)) ?: [] as $line) {
            if ($line !== '') {
                $rows[] = str_getcsv($line, ',', '"', '');
            }
        }

        return $rows;
    }

    /**
     * @param  list<string>  $header
     * @param  array<string, string|null>  $mapping
     * @return array<string, int>
     */
    private function columnIndexes(array $header, array $mapping): array
    {
        $positions = array_flip(array_map(fn ($h) => trim((string) $h), $header));
        $index = [];

        foreach ($mapping as $field => $column) {
            if ($column !== null && isset($positions[$column])) {
                $index[$field] = $positions[$column];
            }
        }

        return $index;
    }

    /**
     * @param  list<string>  $row
     * @param  array<string, int>  $index
     */
    private function value(array $row, array $index, string $field): ?string
    {
        if (! isset($index[$field])) {
            return null;
        }

        $value = trim((string) ($row[$index[$field]] ?? ''));

        return $value === '' ? null : $value;
    }

    private function numeric(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', $value);

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function resolveCompany(?string $name): ?Company
    {
        if ($name === null) {
            return null;
        }

        return Company::firstOrCreate(['name' => $name]);
    }
}
