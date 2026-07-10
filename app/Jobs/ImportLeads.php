<?php

namespace App\Jobs;

use App\Actions\Leads\ImportLeadsFromCsv;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ImportLeads implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string|null>  $mapping
     */
    public function __construct(
        public string $disk,
        public string $path,
        public array $mapping,
    ) {}

    public function handle(ImportLeadsFromCsv $import): void
    {
        $import($this->disk, $this->path, $this->mapping);

        Storage::disk($this->disk)->delete($this->path);
    }
}
