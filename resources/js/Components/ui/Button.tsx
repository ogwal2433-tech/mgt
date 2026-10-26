import { ReactNode, ButtonHTMLAttributes, AnchorHTMLAttributes } from 'react';

type Variant = 'primary' | 'secondary' | 'danger' | 'ghost' | 'success';
type Size = 'sm' | 'md' | 'lg';

const base =
    'inline-flex items-center justify-center gap-2 rounded-md font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-1 disabled:opacity-60 disabled:cursor-not-allowed whitespace-nowrap';

const variants: Record<Variant, string> = {
    primary: 'bg-green-600 text-white hover:bg-green-700 shadow-sm',
    success: 'bg-green-600 text-white hover:bg-green-700 shadow-sm',
    secondary: 'bg-white text-gray-800 border border-gray-300 hover:bg-gray-50 shadow-sm',
    danger: 'bg-red-600 text-white hover:bg-red-700 shadow-sm',
    ghost: 'bg-transparent text-gray-700 hover:bg-gray-100',
};

const sizes: Record<Size, string> = {
    sm: 'px-2.5 py-1.5 text-xs',
    md: 'px-4 py-2 text-sm',
    lg: 'px-5 py-2.5 text-base',
};

export function Spinner({ className = 'h-4 w-4' }: { className?: string }) {
    return (
        <svg className={`animate-spin ${className}`} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
            <path
                className="opacity-90"
                fill="currentColor"
                d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"
            />
        </svg>
    );
}

interface CommonProps {
    variant?: Variant;
    size?: Size;
    loading?: boolean;
    children: ReactNode;
}

type ButtonProps = CommonProps & ButtonHTMLAttributes<HTMLButtonElement>;
type LinkProps = CommonProps & AnchorHTMLAttributes<HTMLAnchorElement>;

export function Button({ variant = 'primary', size = 'md', className = '', children, loading = false, disabled, ...props }: ButtonProps) {
    return (
        <button
            className={`${base} ${variants[variant]} ${sizes[size]} ${loading ? 'relative' : ''} ${className}`}
            disabled={disabled || loading}
            {...props}
        >
            {loading && <Spinner />}
            {children}
        </button>
    );
}

export function LinkButton({ variant = 'primary', size = 'md', className = '', children, loading = false, ...props }: LinkProps) {
    return (
        <a className={`${base} ${variants[variant]} ${sizes[size]} ${className}`} {...props}>
            {loading && <Spinner />}
            {children}
        </a>
    );
}

export default Button;
