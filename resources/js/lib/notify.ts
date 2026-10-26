import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

/**
 * SweetAlert2 self-dismissing notifications — used for every successful action.
 *
 *   Swal.fire({ icon: 'success', title: 'Copied!', timer: 2000, showConfirmButton: false })
 */
const base = {
    timer: 2000,
    showConfirmButton: false,
    customClass: {
        popup: 'rounded-2xl shadow-[0_20px_60px_-15px_rgba(16,24,40,0.35)]',
        title: 'text-ink-900 font-semibold text-base',
        icon: 'mt-1',
    } as Record<string, string>,
};

export function toastSuccess(title = 'Success!'): Promise<unknown> {
    return Swal.fire({ icon: 'success', title, ...base, timer: 2000 });
}

export function toastError(title = 'Something went wrong'): Promise<unknown> {
    return Swal.fire({
        icon: 'error',
        title,
        ...base,
        timer: 3000,
    });
}

export function toastInfo(title = 'Heads up'): Promise<unknown> {
    return Swal.fire({ icon: 'info', title, ...base, timer: 2500 });
}

export function toastWarning(title = 'Please note'): Promise<unknown> {
    return Swal.fire({ icon: 'warning', title, ...base, timer: 3000 });
}

/** Quick confirmation with the Biiteek green action button. */
export async function confirmAction(title: string, text = '', confirmLabel = 'Yes'): Promise<boolean> {
    const result = await Swal.fire({
        title,
        text,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: confirmLabel,
        cancelButtonText: 'Cancel',
        timer: undefined,
        showConfirmButton: true,
        customClass: {
            popup: 'rounded-2xl',
            confirmButton:
                'rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 focus:outline-none',
            cancelButton:
                'rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none ml-2',
        } as Record<string, string>,
        buttonsStyling: false,
    });

    return result.isConfirmed;
}

export default toastSuccess;
