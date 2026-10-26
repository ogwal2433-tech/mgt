import { useEffect, useRef } from 'react';
import { usePage } from '@inertiajs/react';
import { toastSuccess, toastError } from '@/lib/notify';

/**
 * Fires SweetAlert2 toasts for every successful (and failed) action.
 * Server flash messages → self-dismissing Swal notifications.
 */
export function FlashMessages() {
    const { flash } = usePage().props as any;
    const lastSuccess = useRef<string | null>(null);
    const lastError = useRef<string | null>(null);

    useEffect(() => {
        const success = flash?.success ?? null;
        if (success && success !== lastSuccess.current) {
            lastSuccess.current = success;
            toastSuccess(success);
        }
    }, [flash?.success]);

    useEffect(() => {
        const error = flash?.error ?? null;
        if (error && error !== lastError.current) {
            lastError.current = error;
            toastError(error);
        }
    }, [flash?.error]);

    return null;
}

export default FlashMessages;
