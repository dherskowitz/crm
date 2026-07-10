export type Role = 'admin' | 'manager' | 'member';

export type User = {
    id: number;
    name: string;
    email: string;
    role: Role;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

/**
 * Elevated capabilities shared from the backend. Keys mirror
 * App\Enums\Role::allCapabilities(). Capabilities every role has are not
 * represented here.
 */
export type Capability =
    | 'manage_users'
    | 'view_audit_log'
    | 'delete_leads'
    | 'merge_duplicates'
    | 'reassign_leads'
    | 'view_all_leads'
    | 'team_reporting'
    | 'export';

export type Capabilities = Record<Capability, boolean>;

export type Auth = {
    user: User;
    can: Capabilities;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
