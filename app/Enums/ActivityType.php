<?php

namespace App\Enums;

enum ActivityType: string
{
    case Note = 'note';
    case Call = 'call';
    case Email = 'email';
    case StageChange = 'stage_change';
    case FormSubmission = 'form_submission';
    case Merge = 'merge';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Note => 'Note',
            self::Call => 'Call',
            self::Email => 'Email',
            self::StageChange => 'Stage Change',
            self::FormSubmission => 'Form Submission',
            self::Merge => 'Merge',
            self::System => 'System',
        };
    }

    /**
     * Whether this activity type stores a Tiptap JSON document in `body`
     * (notes) rather than plain text.
     */
    public function usesRichText(): bool
    {
        return $this === self::Note;
    }
}
