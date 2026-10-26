export function Pagination({
    meta,
    onGoTo,
}: {
    meta?: { current_page: number; last_page: number; from: number | null; to: number | null; total: number };
    onGoTo: (page: number) => void;
}) {
    if (!meta || meta.last_page <= 1) {
        return null;
    }

    const pages: number[] = [];
    const start = Math.max(1, meta.current_page - 2);
    const end = Math.min(meta.last_page, meta.current_page + 2);
    for (let i = start; i <= end; i++) pages.push(i);

    return (
        <div className="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-4 py-3 sm:flex-row">
            <p className="text-xs text-gray-500">
                Showing <span className="font-medium text-gray-700">{meta.from ?? 0}</span> to{' '}
                <span className="font-medium text-gray-700">{meta.to ?? 0}</span> of{' '}
                <span className="font-medium text-gray-700">{meta.total}</span> records
            </p>
            <div className="flex items-center gap-1">
                <button
                    type="button"
                    onClick={() => onGoTo(meta.current_page - 1)}
                    disabled={meta.current_page <= 1}
                    className="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-40"
                >
                    Previous
                </button>
                {pages.map((page) => (
                    <button
                        key={page}
                        type="button"
                        onClick={() => onGoTo(page)}
                        className={`rounded-md px-3 py-1.5 text-xs font-medium ${
                            page === meta.current_page
                                ? 'bg-green-600 text-white'
                                : 'border border-gray-300 text-gray-700 hover:bg-gray-50'
                        }`}
                    >
                        {page}
                    </button>
                ))}
                <button
                    type="button"
                    onClick={() => onGoTo(meta.current_page + 1)}
                    disabled={meta.current_page >= meta.last_page}
                    className="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-40"
                >
                    Next
                </button>
            </div>
        </div>
    );
}

export default Pagination;
