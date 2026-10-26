import { ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes, InputHTMLAttributes, forwardRef, useState } from 'react';

export function Field({
    label,
    htmlFor,
    error,
    hint,
    required,
    children,
    className = '',
}: {
    label: string;
    htmlFor?: string;
    error?: string;
    hint?: ReactNode;
    required?: boolean;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className={className}>
            <label htmlFor={htmlFor} className="mb-1.5 block text-[13px] font-semibold text-ink-700">
                {label}
                {required && <span className="ml-1 text-red-600">*</span>}
            </label>
            {children}
            {hint && !error && <p className="mt-1.5 text-xs leading-relaxed text-ink-500">{hint}</p>}
            {error && <p className="mt-1.5 text-xs font-medium text-red-600">{error}</p>}
        </div>
    );
}

const controlBase =
    'block w-full rounded-lg border border-gray-300/90 bg-white px-3 py-2 text-[13px] text-ink-900 placeholder-ink-400 shadow-sm transition-colors focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25 focus:outline-none disabled:bg-ink-50 disabled:text-ink-500';

export const TextInput = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement> & { invalid?: boolean }>(
    function TextInput({ className = '', invalid, ...props }, ref) {
        return <input ref={ref} className={`${controlBase} ${invalid ? 'border-red-500' : ''} ${className}`} {...props} />;
    },
);

export const PasswordInput = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement> & { invalid?: boolean }>(
    function PasswordInput({ className = '', invalid, ...props }, ref) {
        const [visible, setVisible] = useState(false);

        return (
            <div className="relative">
                <input
                    ref={ref}
                    type={visible ? 'text' : 'password'}
                    className={`${controlBase} pr-11 ${invalid ? 'border-red-500' : ''} ${className}`}
                    {...props}
                />
                <button
                    type="button"
                    onClick={() => setVisible((v) => !v)}
                    className="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-ink-400 transition-colors hover:text-ink-700 focus:outline-none"
                    aria-label={visible ? 'Hide password' : 'Show password'}
                    tabIndex={-1}
                >
                    {visible ? (
                        // Eye off
                        <svg className="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.8}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12c1.292 4.338 5.31 7.5 10.066 7.5 1.674 0 3.24-.37 4.62-1.04M21.07 12a10.477 10.477 0 00-2.044-3.777M9.88 9.88a3 3 0 004.24 4.24M6.1 6.1l11.8 11.8" />
                        </svg>
                    ) : (
                        // Eye
                        <svg className="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.8}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    )}
                </button>
            </div>
        );
    },
);

export const SelectInput = forwardRef<HTMLSelectElement, SelectHTMLAttributes<HTMLSelectElement> & { invalid?: boolean }>(
    function SelectInput({ className = '', invalid, children, ...props }, ref) {
        return (
            <select ref={ref} className={`${controlBase} ${invalid ? 'border-red-500' : ''} ${className}`} {...props}>
                {children}
            </select>
        );
    },
);

export const TextArea = forwardRef<HTMLTextAreaElement, TextareaHTMLAttributes<HTMLTextAreaElement> & { invalid?: boolean }>(
    function TextArea({ className = '', invalid, ...props }, ref) {
        return <textarea ref={ref} className={`${controlBase} ${invalid ? 'border-red-500' : ''} ${className}`} {...props} />;
    },
);
