import { Head, useForm, usePage } from '@inertiajs/react';
import OperatorLayout from '../../layouts/OperatorLayout';

export default function SelectLine() {
    const { lines = [] } = usePage().props;

    return (
        <>
            <Head title="Select Production Line" />
            <div className="max-w-6xl mx-auto">
                <div className="mb-6">
                    <h1 className="text-3xl font-bold text-gray-800 dark:text-gray-100">Select Production Line</h1>
                    <p className="text-gray-600 dark:text-gray-400 mt-2">Choose a production line and optionally a workstation</p>
                </div>

                {lines.length === 0 ? (
                    <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm text-center py-12 px-6">
                        <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <h3 className="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No lines assigned</h3>
                        <p className="mt-1 text-sm text-gray-500">You are not assigned to any production lines. Please contact your administrator.</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {lines.map((line) => <LineCard key={line.id} line={line} />)}
                    </div>
                )}
            </div>
        </>
    );
}

function LineCard({ line }) {
    const form = useForm({ line_id: line.id, workstation_id: '' });
    const submit = (e) => {
        e.preventDefault();
        form.post('/operator/select-line');
    };

    return (
        <form onSubmit={submit} className="transform transition hover:scale-105">
            <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-6 hover:shadow-xl transition-all">
                <div className="flex items-center justify-between mb-4">
                    <h3 className="text-xl font-bold text-gray-800 dark:text-gray-100">{line.name}</h3>
                    <span className="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">Active</span>
                </div>

                {line.description && <p className="text-gray-600 dark:text-gray-400 mb-4">{line.description}</p>}

                <div className="border-t border-gray-200 dark:border-gray-600 pt-4 mb-4">
                    {line.workstations.length > 0 ? (
                        <>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Workstation <span className="text-gray-400 font-normal">(optional)</span>
                            </label>
                            <select
                                value={form.data.workstation_id}
                                onChange={(e) => form.setData('workstation_id', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 outline-none"
                            >
                                <option value="">All workstations</option>
                                {line.workstations.map((ws) => (
                                    <option key={ws.id} value={ws.id}>{ws.name}{ws.code ? ` (${ws.code})` : ''}</option>
                                ))}
                            </select>
                        </>
                    ) : (
                        <div className="flex items-center text-sm text-gray-500">
                            <svg className="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            <span>No workstations</span>
                        </div>
                    )}
                </div>

                <button
                    type="submit"
                    disabled={form.processing}
                    className="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg py-3 text-base flex items-center justify-center gap-2 disabled:opacity-50"
                >
                    <span>Select</span>
                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
        </form>
    );
}

SelectLine.layout = (page) => <OperatorLayout>{page}</OperatorLayout>;
