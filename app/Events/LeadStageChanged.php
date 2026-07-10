<?php

namespace App\Events;

use App\Enums\Stage;
use App\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;

class LeadStageChanged
{
    use Dispatchable;

    public function __construct(
        public Lead $lead,
        public Stage $from,
        public Stage $to,
    ) {}
}
