import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Button, Icon, TextField } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import { conditionSummary, sourceLabel, priorityRuleFields, priorityRuleInitial } from './fields';
import { __ } from '../../../lib/i18n';

function BandEditor({ bands, basePath }) {
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
        // transform() mutates and returns void in Inertia v3 — chaining .post()
        // off it threw, so this button never saved.
        form.transform((d) => ({ bands: d.bands.map((b) => Number(b)) }));
        form.post(`${basePath}/bands`, { preserveScroll: true });
    };

    // Row descriptors: P1..P4 are "≤ threshold[i]", P5 is "> threshold[3]".
    return (
        <form onSubmit={submit} className="bg-om-card border border-om-line rounded-om p-5 mb-5 max-w-2xl">
            <h2 className="flex items-center gap-2 text-[15px] font-semibold text-om-ink mb-2"><Icon name="sliders-horizontal" size={17} className="text-om-muted" />{__('Score → Priority mapping')}</h2>
            <p className="text-[13px] text-om-muted mb-4">{__('A work order\'s summed score maps to a 1–5 priority using these upper bounds.')}</p>
            <div className="space-y-2">
                {[0, 1, 2, 3].map((i) => (
                    <div key={i} className="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] items-center gap-3 text-[13px] text-om-ink">
                        <span className="text-om-muted">{__('Score ≤')}</span>
                        <TextField
                            type="number"
                            aria-label={__('Priority :n', { n: i + 1 })}
                            value={form.data.bands[i] ?? ''}
                            onChange={(value) => setBand(i, value)}
                            className="min-w-0"
                            error={form.errors[`bands.${i}`]}
                            required
                        />
                        <span className="inline-flex items-center gap-2 font-medium"><Icon name="arrow-right" size={14} className="text-om-faint" />{__('Priority :n', { n: i + 1 })}</span>
                    </div>
                ))}
                <div className="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] items-center gap-3 text-[13px] text-om-ink">
                    <span className="text-om-muted">{__('Otherwise')}</span>
                    <span />
                    <span className="inline-flex items-center gap-2 font-medium"><Icon name="arrow-right" size={14} className="text-om-faint" />{__('Priority :n', { n: 5 })}</span>
                </div>
            </div>
            {form.errors.bands && <p className="mt-2 text-[11.5px] text-om-blocked">{form.errors.bands}</p>}
            {hasBlank && <p className="mt-2 text-[11.5px] text-om-muted">{__('Fill in every threshold to save.')}</p>}
            <div className="mt-4">
                <Button type="submit" variant="primary" leftIcon={<Icon name="save" size={14} />} loading={form.processing} disabled={hasBlank || form.processing}>
                    {form.processing ? __('Saving…') : __('Save mapping')}
                </Button>
            </div>
        </form>
    );
}

export default function PriorityRulesIndex() {
    const { bands = [20, 40, 60, 80], basePath } = usePage().props;

    const drawer = useResourceDrawer();

    const columns = [
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text' },
        { key: 'field_source', label: __('Source'), className: 'text-om-muted', value: (r) => sourceLabel(r.field_source), render: (r) => sourceLabel(r.field_source) },
        { key: 'condition', label: __('Condition'), className: 'text-om-muted', value: (r) => conditionSummary(r), render: (r) => conditionSummary(r) },
        { key: 'points', label: __('Points'), align: 'right', className: 'font-mono', render: (r) => (Number(r.points) > 0 ? `+${r.points}` : r.points) },
        { key: 'is_active', label: __('Status'), value: (r) => __(r.is_active ? 'Active' : 'Inactive'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), onClick: () => drawer.edit(r) },
        {
            label: r.is_active ? __('Deactivate') : __('Activate'),
            onClick: () => router.post(`${basePath}/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: __('Delete'),
            className: 'text-om-blocked hover:underline',
            confirm: {
                title: __('Delete priority rule ":name"?', { name: r.name }),
                confirmLabel: __('Delete'),
            },
            onClick: () => router.delete(`${basePath}/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Priority Settings')} />
            <div className="w-full px-4 py-5 sm:px-6 sm:py-6">
            <div className="mb-5">
                <h1 className="text-[28px] font-semibold tracking-tight text-om-ink">{__('Priority Settings')}</h1>
                <p className="text-[13px] text-om-muted mt-1">{__('Rules add points to a work order; the total maps to a 1–5 priority. Scoring stays off until at least one active rule exists.')}</p>
            </div>
            <BandEditor bands={bands} basePath={basePath} />
            <div className="overflow-hidden rounded-om border border-om-line bg-om-card">
            <ResourceTable
                shape="priority_rules"
                bodyMaxHeight="none"
                title={__('Scoring rules')}
                createHref={`${basePath}/create`}
                onCreate={drawer.create}
                createLabel={__('New Rule')}
                columns={columns}
                orderBy="sort_order"
                actions={actions}
                emptyText={__('No priority rules yet.')}
            />

            </div>
            </div>

            <ResourceFormDrawer
                {...drawer.props}
                action={basePath}
                fields={priorityRuleFields()}
                initial={priorityRuleInitial}
                title={{ create: __('New Priority Rule'), edit: __('Edit Priority Rule') }}
            />
        </>
    );
}

PriorityRulesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
