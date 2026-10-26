import { ReactNode } from 'react';

const styles: Record<string, string> = {
    green: 'bg-brand-50 text-brand-800 border-brand-200/80',
    red: 'bg-red-50 text-red-700 border-red-200/80',
    amber: 'bg-amber-50 text-amber-800 border-amber-200/80',
    blue: 'bg-blue-50 text-blue-700 border-blue-200/80',
    gray: 'bg-ink-50 text-ink-600 border-ink-200/80',
    indigo: 'bg-indigo-50 text-indigo-700 border-indigo-200/80',
};

export default function Badge({
    children,
    color = 'gray',
    className = '',
}: {
    children: ReactNode;
    color?: keyof typeof styles | string;
    className?: string;
}) {
    return (
        <span
            className={`inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold tracking-[-0.004em] ${styles[color] ?? styles.gray} ${className}`}
        >
            <span className="h-1 w-1 rounded-full bg-current opacity-50" />
            {children}
        </span>
    );
}

export function statusColor(status: string): string {
    const s = (status || '').toLowerCase();
    if (s.includes('reject') || s.includes('cancel') || s.includes('fail') || s.includes('expired')) return 'red';
    if (s.includes('pending') || s.includes('await') || s.includes('draft') || s.includes('returned') || s.includes('expiring')) return 'amber';
    if (s.includes('approved') || s.includes('complete') || s.includes('closed') || s.includes('active') || s.includes('success') || s.includes('sent')) return 'green';
    if (s.includes('disburse') || s.includes('payment') || s.includes('review') || s.includes('progress')) return 'blue';
    return 'gray';
}
