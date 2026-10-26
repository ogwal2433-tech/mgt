import { ReactNode } from 'react';

const taglines = [
    'Gain Digital Advantage',
    'Creative Ideas, Smart Tech, Proven Solutions',
    'Innovation, Security, Digital Excellence',
    'Tech-Driven, Insight-Led, Future-Ready',
];

export default function GuestLayout({ children }: { children: ReactNode }) {
    return (
        <div className="flex min-h-screen flex-col bg-gray-50 lg:flex-row">
            {/* Brand panel */}
            <div className="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-ink-950 p-12 text-white lg:flex">
                <div
                    className="pointer-events-none absolute inset-0 opacity-[0.55]"
                    style={{
                        background:
                            'radial-gradient(720px 420px at 18% 12%, rgba(34,197,94,0.22), transparent 60%), radial-gradient(680px 480px at 88% 78%, rgba(16,185,129,0.18), transparent 62%)',
                    }}
                />
                <div className="relative">
                    <div className="flex items-center gap-3">
                        <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-600 text-lg font-extrabold text-white shadow-lg shadow-brand-600/30">
                            B
                        </div>
                        <div>
                            <p className="text-xl font-extrabold tracking-tight">Biiteek</p>
                            <p className="text-xs font-medium uppercase tracking-widest text-brand-300">
                                Gain Digital Advantage
                            </p>
                        </div>
                    </div>

                    <h1 className="mt-14 max-w-md text-5xl font-extrabold leading-[1.08] tracking-tight">
                        Creative Ideas,
                        <br />
                        Smart Tech,
                        <br />
                        <span className="text-brand-400">Proven Solutions</span>
                    </h1>

                    <p className="mt-6 max-w-md text-base leading-relaxed text-ink-300">
                        We combine imaginative thinking with advanced technology to deliver solutions
                        that work reliably and drive measurable results.
                    </p>
                </div>

                <div className="relative space-y-3">
                    {taglines.map((tagline) => (
                        <div key={tagline} className="flex items-start gap-3">
                            <span className="mt-1.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-brand-400" />
                            <p className="text-sm text-ink-300">{tagline}</p>
                        </div>
                    ))}
                </div>
            </div>

            {/* Form panel */}
            <div className="flex flex-1 flex-col">
                <div className="flex flex-1 items-center justify-center p-6">
                    <div className="w-full max-w-md">
                        <div className="mb-8 flex items-center gap-3 lg:hidden">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-base font-extrabold text-white">
                                B
                            </div>
                            <div>
                                <p className="text-lg font-extrabold tracking-tight text-ink-900">Biiteek</p>
                                <p className="text-[11px] font-medium uppercase tracking-widest text-brand-700">
                                    Gain Digital Advantage
                                </p>
                            </div>
                        </div>

                        <div className="rounded-2xl border border-gray-200/80 bg-white p-8 shadow-[0_1px_2px_rgba(16,24,40,0.04),0_12px_32px_-12px_rgba(16,24,40,0.10)]">
                            {children}
                        </div>
                    </div>
                </div>

                {/* Footer */}
                <footer className="border-t border-gray-200 bg-white px-6 py-5">
                    <div className="mx-auto flex max-w-5xl flex-col items-center justify-between gap-3 text-xs text-ink-500 sm:flex-row">
                        <div className="flex items-center gap-2">
                            <span className="flex h-6 w-6 items-center justify-center rounded-md bg-brand-600 text-[10px] font-extrabold text-white">
                                B
                            </span>
                            <p>
                                © {new Date().getFullYear()} <span className="font-semibold text-ink-700">Biiteek</span>.
                                Innovation, Security, Digital Excellence.
                            </p>
                        </div>
                        <nav className="flex flex-wrap items-center justify-center gap-x-5 gap-y-1">
                            <a href="mailto:support@biiteek.example" className="font-medium text-ink-500 transition-colors hover:text-brand-700">
                                Support
                            </a>
                            <span className="hidden text-ink-300 sm:inline">·</span>
                            <span className="font-medium text-ink-500">Management System</span>
                            <span className="hidden text-ink-300 sm:inline">·</span>
                            <span className="font-medium text-ink-500">v1.0</span>
                        </nav>
                    </div>
                </footer>
            </div>
        </div>
    );
}
