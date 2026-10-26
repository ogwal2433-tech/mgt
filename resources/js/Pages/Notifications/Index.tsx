import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/ui/Pagination';
import Button from '@/Components/ui/Button';
import { formatDateTime } from '@/types/app';
import { Head, Link, router } from '@inertiajs/react';

export default function NotificationsIndex({ notifications, unread }: any) {
    return (
        <AppLayout
            title="Notifications"
            breadcrumb={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Notifications' }]}
            actions={<Button variant="secondary" onClick={() => router.post('/notifications/read-all')}>Mark all as read</Button>}
        >
            <Head title="Notifications" />

            <div className="rounded-lg border border-gray-200 bg-white shadow-sm">
                <div className="border-b border-gray-100 px-5 py-4">
                    <p className="text-sm text-gray-500">{unread} unread notification{unread === 1 ? '' : 's'}</p>
                </div>
                <ul className="divide-y divide-gray-100">
                    {(notifications?.data ?? []).length === 0 && (
                        <li className="px-5 py-12 text-center text-sm text-gray-500">No notifications yet.</li>
                    )}
                    {(notifications?.data ?? []).map((n: any) => (
                        <li key={n.id} className={`px-5 py-4 ${n.read_at ? '' : 'bg-green-50/50'}`}>
                            <div className="flex items-start justify-between gap-4">
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <p className="text-sm font-semibold text-gray-900">{n.title}</p>
                                        {!n.read_at && <span className="h-2 w-2 rounded-full bg-green-600" />}
                                    </div>
                                    <p className="mt-1 text-sm text-gray-700">{n.message}</p>
                                    <p className="mt-1 text-xs text-gray-500">{formatDateTime(n.created_at)} · {n.type}</p>
                                </div>
                                <div className="flex flex-shrink-0 items-center gap-2">
                                    {n.link && (
                                        <Link href={n.link} className="text-sm font-semibold text-green-700 hover:underline">Open</Link>
                                    )}
                                    {!n.read_at && (
                                        <Button size="sm" variant="ghost" onClick={() => router.post(`/notifications/${n.id}/read`)}>Mark read</Button>
                                    )}
                                </div>
                            </div>
                        </li>
                    ))}
                </ul>
                <Pagination meta={notifications} onGoTo={(page) => router.get('/notifications', { page }, { preserveState: true })} />
            </div>
        </AppLayout>
    );
}
