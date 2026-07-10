import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Button } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import { conditionSummary, sourceLabel } from './fields';
import { __ } from '../../../lib/i18n';

function BandEditor({ bands }) {
    const form = useForm({ bands: bands.map((b) => String(b)) });
    const setBand = (i, v) => {
        const next = [...form.data.bands];
        next[i] = v;
        form.setData('bands', next);
    };
    // A blank field must not silently become 0 — require every threshold.
    const hasBlank = form.data.bands.some((b) => String(b).trim() === '');
    const submit = (e) => {
        e.preventDefault();
        if (hasBlank) return;
        form.transform((d) => ({ bands: d.bands.map((b) => Number(b)) }))
            .post('/admin/priority-rules/bands', { preserveScroll: true });
    };

    // Row descriptors: P1..P4 are "≤ threshold[i]", P5 is "> threshold[3]".
    return (
        <form onSubmit={submit} className="bg-om-card border border-om-line rounded-om p-6 mb-6 max-w-2xl">
            <h2 className="text-lg font-bold text-om-ink mb-1">{__('Score → Priority mapping')}</h2>
            <p className="text-[13px] text-om-muted mb-4">{__('A work order\'s summed score maps to a 1–5 priority using these upper bounds.')}</p>
            <div className="space-y-2">
                {[0, 1, 2, 3].map((i) => (
                    <div key={i} className="flex items-center gap-3 text-[13px] text-om-ink">
                        <span className="w-24 text-om-muted">{__('Score ≤')}</span>
                        <input
                            type="number"
                            value={form.data.bands[i] ?? ''}
                            onChange={(e) => setBand(i, e.target.value)}
                            className="w-28 bg-om-bg border border-om-line rounded-om-sm px-3 py-2 text-[13px] text-om-ink outline-none focus:border-om-accent"
                        />
                        <span className="font-medium">→ {__('Priority :n', { n: i + 1 })}</span>
                    </div>
                ))}
                <div className="flex items-center gap-3 text-[13px] text-om-ink">
                    <span className="w-24 text-om-muted">{__('Otherwise')}</span>
                    <span className="w-28" />
                    <span className="font-medium">→ {__('Priority :n', { n: 5 })}</span>
                </div>
            </div>
            {form.errors.bands && <p className="mt-2 text-[11.5px] text-om-blocked">{form.errors.bands}</p>}
            {hasBlank && <p className="mt-2 text-[11.5px] text-om-muted">{__('Fill in every threshold to save.')}</p>}
            <div className="mt-4">
                <Button type="submit" variant="primary" loading={form.processing} disabled={hasBlank || form.processing}>
                    {form.processing ? __('Saving…') : __('Save mapping')}
                </Button>
            </div>
        </form>
    );
}

export default function PriorityRulesIndex() {
    const { bands = [20, 40, 60, 80] } = usePage().props;

    const columns = [
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink' },
        { key: 'field_source', label: __('Source'), className: 'text-om-muted', render: (r) => sourceLabel(r.field_source) },
        { key: 'condition', label: __('Condition'), className: 'text-om-muted', render: (r) => conditionSummary(r) },
        { key: 'points', label: __('Points'), align: 'right', className: 'font-mono', render: (r) => (Number(r.points) > 0 ? `+${r.points}` : r.points) },
        { key: 'is_active', label: __('Status'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), href: `/admin/priority-rules/${r.id}/edit` },
        {
            label: r.is_active ? __('Deactivate') : __('Activate'),
            onClick: () => router.post(`/admin/priority-rules/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: __('Delete'),
            className: 'text-om-blocked hover:underline',
            onClick: () => {
                if (confirm(__('Delete priority rule ":name"?', { name: r.name }))) {
                    router.delete(`/admin/priority-rules/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title={__('Priority Settings')} />
            <div className="mb-2">
                <h1 className="text-3xl font-bold text-om-ink">{__('Priority Settings')}</h1>
                <p className="text-[13px] text-om-muted mt-1">{__('Rules add points to a work order; the total maps to a 1–5 priority. Scoring stays off until at least one active rule exists.')}</p>
            </div>
            <BandEditor bands={bands} />
            <ResourceTable
                shape="priority_rules"
                title={__('Scoring rules')}
                createHref="/admin/priority-rules/create"
                createLabel={__('+ New Rule')}
                columns={columns}
                orderBy="sort_order"
                actions={actions}
                emptyText={__('No priority rules yet.')}
            />
        </>
    );
}

PriorityRulesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
