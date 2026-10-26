export interface AuthUser {
    id: number;
    name: string;
    username: string;
    email: string;
    is_active: boolean;
    must_change_password: boolean;
    employee: {
        id: number;
        employee_code: string;
        full_name: string;
        job_title: string | null;
        department: { id: number; code: string; name: string } | null;
    } | null;
    roles: { slug: string; name: string }[];
    permissions: string[];
}

export interface NavItem {
    label: string;
    href: string;
    icon: string;
    show: boolean;
    badge?: number;
}

export interface PageMeta {
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export function can(user: AuthUser | null | undefined, permission: string): boolean {
    return !!user?.permissions?.includes(permission);
}

export function canAny(user: AuthUser | null | undefined, permissions: string[]): boolean {
    return permissions.some((p) => can(user, p));
}

export function formatMoney(amount: number | string | null | undefined, currency = 'USD'): string {
    if (amount === null || amount === undefined || amount === '') return `0.00 ${currency}`;
    const n = typeof amount === 'string' ? parseFloat(amount) : amount;
    if (Number.isNaN(n)) return `${amount} ${currency}`;
    return `${n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}`;
}

export function formatDate(value: string | null | undefined): string {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

export function formatDateTime(value: string | null | undefined): string {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
