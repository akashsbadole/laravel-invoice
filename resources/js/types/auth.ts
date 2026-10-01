export type UserRole = 'admin' | 'invoice_creator' | 'viewer';

export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    role: UserRole;
    is_active: boolean;
    email_verified_at: string | null;
    /* @chisel-2fa */
    two_factor_enabled?: boolean;
    /* @end-chisel-2fa */
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Tenant = {
    id: number;
    name: string;
    slug: string;
    status: string;
    trial_ends_at: string | null;
    subscription: {
        status: string;
        plan_name: string | null;
        trial_ends_at: string | null;
        current_period_ends_at: string | null;
    } | null;
};

export type Firm = {
    id: number;
    name: string;
    slug: string;
};

export type Auth = {
    user: User;
    tenant: Tenant | null;
    firms: Firm[];
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

/* @chisel-2fa */
export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
/* @end-chisel-2fa */
