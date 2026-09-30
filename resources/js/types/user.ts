export type ManagedUser = {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'invoice_creator' | 'viewer';
    is_active: boolean;
    last_login_at: string | null;
    created_at: string;
};

export type RoleOption = { value: string; label: string };
