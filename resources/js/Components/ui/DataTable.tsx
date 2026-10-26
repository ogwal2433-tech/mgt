import { ReactNode } from 'react';

export interface Column<T> {
    key: string;
    header: ReactNode;
    render?: (row: T) => ReactNode;
    className?: string;
    sortable?: boolean;
}

export function DataTable<T extends Record<string, any>>({
    columns,
    rows,
    keyExtractor,
    emptyMessage = 'No records found.',
    loading = false,
}: {
    columns: Column<T>[];
    rows: T[];
    keyExtractor: (row: T, index: number) => string | number;
    emptyMessage?: string;
    loading?: boolean;
}) {
    return (
        <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200 text-[13px]">
                <thead>
                    <tr>
                        {columns.map((col) => (
                            <th
                                key={col.key}
                                scope="col"
                                className={`whitespace-nowrap px-4 py-2.5 text-left text-2xs font-semibold uppercase tracking-wider text-ink-500 ${col.className ?? ''}`}
                            >
                                {col.header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 bg-white">
                    {loading ? (
                        <tr>
                            <td colSpan={columns.length} className="px-4 py-12 text-center">
                                <div className="flex items-center justify-center gap-2 text-ink-500">
                                    <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                        <path className="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z" />
                                    </svg>
                                    <span className="text-sm">Loading…</span>
                                </div>
                            </td>
                        </tr>
                    ) : rows.length === 0 ? (
                        <tr>
                            <td colSpan={columns.length} className="px-4 py-14 text-center">
                                <div className="mx-auto max-w-sm">
                                    <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-ink-50 text-ink-400">
                                        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M20 13V7a2 2 0 00-2-2H6a2 2 0 00-2 2v6m16 0v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4m16 0h-2M4 13h2" />
                                        </svg>
                                    </div>
                                    <p className="text-sm font-medium text-ink-700">{emptyMessage}</p>
                                </div>
                            </td>
                        </tr>
                    ) : (
                        rows.map((row, index) => (
                            <tr key={keyExtractor(row, index)} className="transition-colors hover:bg-brand-50/40">
                                {columns.map((col) => (
                                    <td key={col.key} className={`px-4 py-2.5 align-middle text-ink-800 ${col.className ?? ''}`}>
                                        {col.render ? col.render(row) : (row[col.key] as ReactNode)}
                                    </td>
                                ))}
                            </tr>
                        ))
                    )}
                </tbody>
            </table>
        </div>
    );
}

export default DataTable;
