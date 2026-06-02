import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';

export default function WorkstationCreate() {
    const { line } = usePage().props;
    const form = useForm({
        code: '',
        name: '',
        workstation_type: '',
        is_active: true,
    });

    const submit = (e) => {
        e.preventDefault();
        form.post(`/admin/lines/${line.id}/workstations`);
    };

    return (
        <div className="max-w-2xl mx-auto">
            <Head title="Create Workstation" />

            <div className="mb-6">
                <Link
                    href={`/admin/lines/${line.id}/workstations`}
                    className="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4 text-sm"
                >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Back to Workstations
                </Link>
                <h1 className="text-3xl font-bold text-gray-800">Create Workstation</h1>
                <p className="text-sm text-gray-600 mt-1">{line.name}</p>
            </div>

            <form onSubmit={submit} className="bg-white rounded-lg shadow-sm p-6 space-y-5">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Workstation Code <span className="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        value={form.data.code}
                        onChange={(e) => form.setData('code', e.target.value)}
                        placeholder="e.g., WS-A01, ASSEMBLY-1"
                        className="form-input w-full"
                        required
                        autoFocus
                    />
                    <p className="text-sm text-gray-500 mt-1">Unique identifier for this workstation</p>
                    {form.errors.code && <p className="mt-1 text-xs text-red-600">{form.errors.code}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Workstation Name <span className="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="e.g., Assembly Station 1, Quality Check Point"
                        className="form-input w-full"
                        required
                    />
                    {form.errors.name && <p className="mt-1 text-xs text-red-600">{form.errors.name}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Workstation Type
                    </label>
                    <input
                        type="text"
                        value={form.data.workstation_type}
                        onChange={(e) => form.setData('workstation_type', e.target.value)}
                        placeholder="e.g., Assembly, Quality Control, Packaging (optional)"
                        className="form-input w-full"
                    />
                    <p className="text-sm text-gray-500 mt-1">Optional classification for this workstation</p>
                    {form.errors.workstation_type && <p className="mt-1 text-xs text-red-600">{form.errors.workstation_type}</p>}
                </div>

                <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        checked={form.data.is_active}
                        onChange={(e) => form.setData('is_active', e.target.checked)}
                        className="h-5 w-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
                    />
                    Active (workstation is ready for use)
                </label>

                <div className="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
                    >
                        {form.processing ? 'Creating…' : 'Create Workstation'}
                    </button>
                    <Link
                        href={`/admin/lines/${line.id}/workstations`}
                        className="text-gray-500 hover:text-gray-800 text-sm"
                    >
                        Cancel
                    </Link>
                </div>
            </form>
        </div>
    );
}

WorkstationCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
