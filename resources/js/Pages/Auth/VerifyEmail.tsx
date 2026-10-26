import Button from '@/Components/ui/Button';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/email/verification-notification');
    };

    return (
        <GuestLayout>
            <Head title="Verify email — Biiteek" />

            <div className="mb-7">
                <p className="eyebrow">Email verification</p>
                <h1 className="mt-2 text-3xl font-extrabold tracking-tight text-ink-900">Verify your email</h1>
                <p className="mt-2 text-sm leading-relaxed text-ink-500">
                    Thanks for signing up! Before getting started, please verify your email address by
                    clicking the link we just emailed you.
                </p>
            </div>

            {status === 'verification-link-sent' && (
                <div className="mb-5 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-800">
                    A new verification link has been sent to your email address.
                </div>
            )}

            <form onSubmit={submit}>
                <Button type="submit" loading={processing} className="w-full" size="lg">
                    {processing ? 'Sending…' : 'Resend verification email'}
                </Button>

                <div className="mt-5 flex items-center justify-between text-xs">
                    <a href="/profile" className="link-clean">
                        Edit profile
                    </a>
                    <form method="POST" action="/logout">
                        <button type="submit" className="font-semibold text-ink-500 transition-colors hover:text-red-600">
                            Sign out
                        </button>
                    </form>
                </div>
            </form>
        </GuestLayout>
    );
}
