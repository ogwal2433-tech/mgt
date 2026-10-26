import Checkbox from '@/Components/Checkbox';
import { Field, PasswordInput, TextInput } from '@/Components/ui/Form';
import Button from '@/Components/ui/Button';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Login({ status }: { status?: string; canResetPassword: boolean }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        login: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/login', { onFinish: () => reset('password') });
    };

    return (
        <GuestLayout>
            <Head title="Sign in — Biiteek" />

            <div className="mb-7">
                <p className="eyebrow">Management System</p>
                <h1 className="mt-2 text-3xl font-extrabold tracking-tight text-ink-900">Welcome back</h1>
                <p className="mt-2 text-sm leading-relaxed text-ink-500">
                    Sign in with your employee username or company email.
                </p>
            </div>

            {status && (
                <div className="mb-5 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-800">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <Field label="Username or email" htmlFor="login" error={errors.login} required>
                    <TextInput
                        id="login"
                        name="login"
                        value={data.login}
                        autoFocus
                        autoComplete="username"
                        placeholder="e.g. jdoe"
                        onChange={(e) => setData('login', e.target.value)}
                    />
                </Field>

                <Field label="Password" htmlFor="password" error={errors.password} required>
                    <PasswordInput
                        id="password"
                        name="password"
                        value={data.password}
                        autoComplete="current-password"
                        placeholder="••••••••"
                        onChange={(e) => setData('password', e.target.value)}
                    />
                </Field>

                <div className="flex items-center justify-between">
                    <label className="flex cursor-pointer items-center gap-2.5">
                        <Checkbox name="remember" checked={data.remember} onChange={(e) => setData('remember', (e.target.checked || false) as false)} />
                        <span className="text-sm text-ink-600">Keep me signed in</span>
                    </label>
                    <a href="/forgot-password" className="text-sm font-semibold text-brand-700 transition-colors hover:text-brand-800">
                        Forgot password?
                    </a>
                </div>

                <Button type="submit" loading={processing} className="w-full" size="lg">
                    {processing ? 'Signing in…' : 'Sign in'}
                </Button>
            </form>

            <p className="mt-6 border-t border-gray-100 pt-5 text-xs leading-relaxed text-ink-500">
                Accounts are issued by Human Resources or your system administrator. Need access?
                Contact your department head.
            </p>
        </GuestLayout>
    );
}
