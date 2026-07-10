<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Role: string
{
    use HasOptions;

    case Admin = 'admin';
    case Manager = 'manager';
    case Member = 'member';

    /**
     * Human-readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Manager => 'Manager',
            self::Member => 'Member',
        };
    }

    /**
     * The elevated capabilities granted to this role. These are the single
     * source of truth consumed by both Gates and the Inertia capability map.
     * Capabilities every role shares (claim, create/edit visible leads, log
     * activities, see own reporting) are intentionally omitted.
     *
     * @return list<string>
     */
    public function capabilities(): array
    {
        return match ($this) {
            self::Admin => [
                'manage_users',
                'view_audit_log',
                'delete_leads',
                'merge_duplicates',
                'reassign_leads',
                'view_all_leads',
                'team_reporting',
                'export',
            ],
            self::Manager => [
                'merge_duplicates',
                'reassign_leads',
                'view_all_leads',
                'team_reporting',
                'export',
            ],
            self::Member => [],
        };
    }

    /**
     * Whether this role grants the given capability.
     */
    public function can(string $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }

    /**
     * All capabilities the application recognises, used to build the
     * frontend capability map.
     *
     * @return list<string>
     */
    public static function allCapabilities(): array
    {
        return self::Admin->capabilities();
    }
}
