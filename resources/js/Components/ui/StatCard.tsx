import { ReactNode } from 'react';

export function StatCard({
    label,
    value,
    hint,
    icon,
    accent = 'green',
    href,
}: {
    label: string;
    value: ReactNode;
    hint?: ReactNode;
    icon?: ReactNode;
    accent?: 'green' | 'blue' | 'amber' | 'red' | 'gray';
    href?: string;
}) {
    const accents: Record<string, string> = {
        green: 'bg-brand-50 text-brand-700',
        blue: 'bg-blue-50 text-blue-700',
        amber: 'bg-amber-50 text-amber-700',
        red: 'bg-red-50 text-red-700',
        gray: 'bg-ink-50 text-ink-600',
    };

    const body = (
        <div className="group rounded-xl border border-gray-200/80 bg-white p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04)] transition-all hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-[0_10px_28px_-12px_rgba(16,24,40,0.14)]">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="eyebrow truncate">{label}</p>
                    <p className="mt-2 text-2xl font-extrabold tracking-tight text-ink-900 tabular">{value}</p>
                    {hint && <p className="mt-1 text-xs leading-relaxed text-ink-500">{hint}</p>}
                </div>
                {icon && (
                    <div className={`flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg ${accents[accent]}`}>
                        {icon}
                    </div>
                )}
            </div>
        </div>
    );

    return href ? <a href={href}>{body}</a> : body;
}

export default StatCard;
