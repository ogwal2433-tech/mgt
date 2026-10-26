import { ReactNode, useState } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { AuthUser, NavItem } from '@/types/app';
import FlashMessages from '@/Components/ui/FlashMessages';
import Icon from '@/Components/ui/Icon';

export default function AppLayout({
    children,
    title,
    actions,
    breadcrumb,
}: {
    children: ReactNode;
    title?: string;
    actions?: ReactNode;
    breadcrumb?: { label: string; href?: string }[];
}) {
    const { auth, navigation, unreadNotifications, app } = usePage().props as any;
    const user = auth?.user as AuthUser | null;
    const nav: NavItem[] = navigation ?? [];
    const [mobileOpen, setMobileOpen] = useState(false);

    const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';
    const isActive = (href: string) => currentPath === href || currentPath.startsWith(href + '/');

    const sidebar = (
        <div className="flex h-full flex-col bg-white">
            <div className="flex h-16 items-center gap-3 border-b border-gray-100 px-5">
                <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-600 text-sm font-extrabold text-white shadow-sm shadow-brand-600/25">
                    B
                </div>
                <div>
                    <p className="text-sm font-extrabold leading-tight tracking-tight text-ink-900">Biiteek</p>
                    <p className="text-[10px] font-medium uppercase tracking-wider text-ink-500">Management System</p>
                </div>
            </div>

            <nav className="flex-1 space-y-0.5 overflow-y-auto px-3 py-4">
                {nav.map((item) => (
                    <Link
                        key={item.href}
                        href={item.href}
                        onClick={() => setMobileOpen(false)}
                        className={`flex items-center gap-3 rounded-md px-3 py-2 text-[13px] font-medium transition-colors ${
                            isActive(item.href)
                                ? 'bg-brand-600 text-white shadow-sm shadow-brand-600/25'
                                : 'text-ink-700 hover:bg-ink-50 hover:text-ink-900'
                        }`}
                    >
                        <Icon name={item.icon} className={`h-[18px] w-[18px] ${isActive(item.href) ? 'text-white' : 'text-ink-400'}`} />
                        <span className="flex-1 tracking-[-0.004em]">{item.label}</span>
                        {!!item.badge && item.badge > 0 && (
                            <span
                                className={`flex h-5 min-w-[20px] items-center justify-center rounded-full px-1.5 text-[10px] font-bold ${
                                    isActive(item.href) ? 'bg-white text-brand-700' : 'bg-brand-600 text-white'
                                }`}
                            >
                                {item.badge > 99 ? '99+' : item.badge}
                            </span>
                        )}
                    </Link>
                ))}
            </nav>

            <div className="border-t border-gray-100 p-4">
                <div className="flex items-center gap-3">
                    <div className="flex h-9 w-9 items-center justify-center rounded-full bg-ink-900 text-xs font-bold text-white">
                        {(user?.employee?.full_name ?? user?.name ?? 'U')
                            .split(' ')
                            .map((p: string) => p[0])
                            .slice(0, 2)
                            .join('')}
                    </div>
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-[13px] font-semibold text-ink-900">{user?.employee?.full_name ?? user?.name}</p>
                        <p className="truncate text-xs text-ink-500">{user?.roles?.map((r) => r.name).join(', ')}</p>
                    </div>
                </div>
            </div>
        </div>
    );

    return (
        <div className="flex min-h-screen flex-col bg-gray-50">
            <FlashMessages />

            <aside className="fixed inset-y-0 left-0 z-40 hidden w-64 border-r border-gray-200 lg:block">{sidebar}</aside>

            {mobileOpen && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <div className="absolute inset-0 bg-ink-900/50" onClick={() => setMobileOpen(false)} />
                    <aside className="absolute inset-y-0 left-0 w-64 shadow-xl">{sidebar}</aside>
                </div>
            )}

            <div className="flex flex-1 flex-col lg:pl-64">
                <header className="sticky top-0 z-30 flex h-16 items-center justify-between gap-4 border-b border-gray-200 bg-white/95 px-4 backdrop-blur sm:px-6">
                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            className="rounded-md p-2 text-ink-600 hover:bg-ink-100 lg:hidden"
                            onClick={() => setMobileOpen(true)}
                            aria-label="Open navigation"
                        >
                            <Icon name="menu" />
                        </button>
                        {breadcrumb && breadcrumb.length > 0 ? (
                            <nav className="hidden items-center gap-2 text-[13px] text-ink-500 sm:flex">
                                {breadcrumb.map((crumb, i) => (
                                    <span key={i} className="flex items-center gap-2">
                                        {i > 0 && <span className="text-ink-300">/</span>}
                                        {crumb.href ? (
                                            <Link href={crumb.href} className="font-medium transition-colors hover:text-brand-700">
                                                {crumb.label}
                                            </Link>
                                        ) : (
                                            <span className="font-semibold text-ink-900">{crumb.label}</span>
                                        )}
                                    </span>
                                ))}
                            </nav>
                        ) : (
                            <h1 className="text-lg font-bold tracking-tight text-ink-900">{title}</h1>
                        )}
                    </div>

                    <div className="flex items-center gap-2">
                        <Link
                            href="/notifications"
                            className="relative rounded-md p-2 text-ink-600 transition-colors hover:bg-ink-100"
                            aria-label="Notifications"
                        >
                            <Icon name="bell" />
                            {!!unreadNotifications && unreadNotifications > 0 && (
                                <span className="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">
                                    {unreadNotifications > 99 ? '99+' : unreadNotifications}
                                </span>
                            )}
                        </Link>
                        <Link
                            href="/profile"
                            className="hidden items-center gap-2 rounded-md px-3 py-2 text-[13px] font-medium text-ink-700 transition-colors hover:bg-ink-100 sm:flex"
                        >
                            <div className="flex h-6 w-6 items-center justify-center rounded-full bg-ink-900 text-[9px] font-bold text-white">
                                {(user?.employee?.full_name ?? user?.name ?? 'U')
                                    .split(' ')
                                    .map((p: string) => p[0])
                                    .slice(0, 2)
                                    .join('')}
                            </div>
                            Profile
                        </Link>

                        {/* Logout — extreme top-right corner */}
                        <button
                            type="button"
                            onClick={() => router.post('/logout')}
                            className="flex items-center gap-2 rounded-md border border-gray-200 bg-white px-3 py-2 text-[13px] font-semibold text-ink-700 shadow-sm transition-colors hover:border-red-200 hover:bg-red-50 hover:text-red-700"
                            aria-label="Sign out"
                        >
                            <Icon name="logout" className="h-4 w-4" />
                            <span className="hidden sm:inline">Sign out</span>
                        </button>
                    </div>
                </header>

                <main className="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                    {(title || actions) && (
                        <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                {breadcrumb && breadcrumb.length > 0 && (
                                    <h1 className="text-2xl font-extrabold tracking-tight text-ink-900">{title}</h1>
                                )}
                                {breadcrumb && breadcrumb.length > 0 && (
                                    <nav className="mt-1.5 flex items-center gap-2 text-xs text-ink-500">
                                        {breadcrumb.map((crumb, i) => (
                                            <span key={i} className="flex items-center gap-2">
                                                {i > 0 && <span className="text-ink-300">/</span>}
                                                {crumb.href ? (
                                                    <Link href={crumb.href} className="font-medium transition-colors hover:text-brand-700">
                                                        {crumb.label}
                                                    </Link>
                                                ) : (
                                                    <span className="font-semibold text-ink-700">{crumb.label}</span>
                                                )}
                                            </span>
                                        ))}
                                    </nav>
                                )}
                            </div>
                            {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
                        </div>
                    )}
                    {children}
                </main>

                {/* Footer */}
                <footer className="border-t border-gray-200 bg-white px-4 py-4 sm:px-6 lg:px-8">
                    <div className="flex flex-col items-center justify-between gap-2 text-xs text-ink-500 sm:flex-row">
                        <div className="flex items-center gap-2">
                            <span className="flex h-5 w-5 items-center justify-center rounded bg-brand-600 text-[9px] font-extrabold text-white">
                                B
                            </span>
                            <p>
                                © {new Date().getFullYear()} <span className="font-semibold text-ink-700">Biiteek</span> — Tech-Driven, Insight-Led, Future-Ready
                            </p>
                        </div>
                        <nav className="flex items-center gap-4">
                            <Link href="/notifications" className="font-medium transition-colors hover:text-brand-700">
                                Notifications
                            </Link>
                            <Link href="/profile" className="font-medium transition-colors hover:text-brand-700">
                                Profile
                            </Link>
                            <span className="text-ink-300">·</span>
                            <span>{app?.name ?? 'Biiteek'} v1.0</span>
                        </nav>
                    </div>
                </footer>
            </div>
        </div>
    );
}
