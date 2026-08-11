import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
// Explicit extension: see the note in materials/Show.jsx — the helper module
// differs only in case and would resolve wrong on case-insensitive filesystems.
import EngineeringDocuments from '../../../components/EngineeringDocuments.jsx';
import { __ } from '../../../lib/i18n';

export default function SubassemblyShow({ subassembly }) {
    return (
        <>
            <Head title={`${__('Subassembly')} ${subassembly.code}`} />

            <nav className="text-sm text-om-muted mb-4">
                <Link href="/admin/dashboard" className="hover:text-om-ink">{__('Dashboard')}</Link>
                {' / '}
                <Link href="/admin/subassemblies" className="hover:text-om-ink">{__('Subassemblies')}</Link>
                {' / '}
                <span className="text-om-ink">{subassembly.code}</span>
            </nav>

            <div className="flex items-center gap-3 mb-6">
                <h1 className="text-3xl font-bold text-om-ink">{subassembly.name}</h1>
                <span className="text-om-muted font-mono">{subassembly.code}</span>
                {subassembly.is_active
                    ? <span className="inline-block rounded px-2 py-0.5 text-xs bg-om-running-bg text-om-running">{__('Active')}</span>
                    : <span className="inline-block rounded px-2 py-0.5 text-xs bg-gray-200 text-gray-700">{__('Inactive')}</span>}
            </div>

            <div className="card max-w-2xl">
                <h2 className="text-lg font-semibold text-om-ink mb-3">{__('Details')}</h2>
                <dl className="space-y-2">
                    <div className="flex justify-between gap-4">
                        <dt className="text-sm text-om-muted">{__('Product type')}</dt>
                        <dd className="text-sm font-medium text-right">
                            {subassembly.product_type ? `${subassembly.product_type.name} (${subassembly.product_type.code})` : '—'}
                        </dd>
                    </div>
                    {subassembly.description && (
                        <div className="pt-2">
                            <dt className="text-sm text-om-muted mb-1">{__('Description')}</dt>
                            <dd className="text-sm">{subassembly.description}</dd>
                        </div>
                    )}
                </dl>
            </div>

            <EngineeringDocuments entityType="subassembly" entityId={subassembly.id} />
        </>
    );
}

SubassemblyShow.layout = (page) => <AppLayout>{page}</AppLayout>;
