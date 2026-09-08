import { Head, useForm, usePage } from '@inertiajs/react';
import { Button, Dropdown } from '@openmes/ui';
import AppLayout from '../../layouts/AppLayout';
import ResourceTable from '../../components/ResourceTable';
import { __, formatDateTime } from '../../lib/i18n';

/**
 * Pallet logistics overview (#101): where every pallet is, where it should go,
 * and what state it's in. Rows stream live from the `pallets` shape, so a move
 * booked on the shop-floor terminal lands here without a refresh.
 *
 * The re-route card above the table changes a pallet's destination without
 * moving it — the counterpart to "Move Pallet", which changes its location.
 */

const STATUS_BADGE = {
    open: 'bg-om-running-bg text-om-running',
    closed: 'bg-om-chip text-om-accent',
    shipped: 'bg-om-chip text-om-muted',
};

/**
 * Transit state, derived from destination alone: it is cleared on arrival, so a
 * pending destination means in transit and a stamped arrival with no pending
 * destination means the pallet completed its last assigned run.
 */
function transitOf(row) {
    if (row.destination) return 'in_transit';
    return row.arrived_at ? 'arrived' : 'idle';
}

const TRANSIT_BADGE = {
    in_transit: 'bg-om-downtime-bg text-om-downtime',
    arrived: 'bg-om-running-bg text-om-running',
    idle: 'bg-om-chip text-om-muted',
};
const TRANSIT_LABELS = { in_transit: 'In transit', arrived: 'At destination', idle: 'Not assigned' };

/**
 * Text-input metrics matched to the shared Dropdown trigger (px-[13px]
 * py-[10px], 13.5px text) so the four fields in the re-route row line up.
 * The global .form-input is min-h-[48px], which stands ~9px taller than a
 * Dropdown and makes a mixed row look ragged.
 */
const FIELD_CLS =
    'w-full rounded-om-sm border border-om-line bg-om-bg px-[13px] py-[10px] text-[13.5px] '
    + 'text-om-ink placeholder:text-om-faint outline-none transition-colors '
    + 'focus:border-om-accent focus:ring-[3px] focus:ring-om-accent/15';

/** Assign or clear a pallet's destination — an explicit, logged re-route. */
function RerouteCard() {
    const { movablePallets = [], operators = [] } = usePage().props;

    const form = useForm({ pallet_id: '', destination: '', worker_id: '', notes: '' });
    const { data, setData, errors, processing } = form;

    const selected = movablePallets.find((p) => String(p.id) === String(data.pallet_id));

    const submit = (e) => {
        e.preventDefault();
        form.post('/logistics/pallets/destination', {
            preserveScroll: true,
            onSuccess: () => form.reset('pallet_id', 'destination', 'notes'),
        });
    };

    return (
        // Uncapped like the table below it (ResourceTable fullWidth), so the two
        // share the same left/right edge.
        <form onSubmit={submit} className="bg-om-card border border-om-line rounded-om p-5 mb-6">
            <h2 className="text-sm font-semibold text-om-ink mb-1">{__('Set destination')}</h2>
            <p className="text-[12px] text-om-muted mb-4">
                {__('Assign where a pallet should go, or leave the destination blank to clear it.')}
            </p>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <div className="block text-[13px] font-medium text-om-muted mb-1">
                        {__('Pallet')} <span className="text-om-blocked">*</span>
                    </div>
                    <Dropdown
                        aria-label={__('Pallet')}
                        className="w-full"
                        value={data.pallet_id == null ? '' : String(data.pallet_id)}
                        onChange={(v) => setData('pallet_id', v)}
                        placeholder={__('— Select pallet —')}
                        options={movablePallets.map((p) => ({
                            value: String(p.id),
                            label: p.location ? `${p.pallet_no} · ${p.location}` : p.pallet_no,
                        }))}
                    />
                    {selected && (
                        <p className="mt-1 text-[12px] text-om-muted">
                            {__('Current destination')}:{' '}
                            <span className="font-mono">{selected.destination || '—'}</span>
                        </p>
                    )}
                    {errors.pallet_id && <p className="mt-1 text-xs text-om-blocked">{errors.pallet_id}</p>}
                </div>

                <div>
                    <div className="block text-[13px] font-medium text-om-muted mb-1">{__('Destination')}</div>
                    <input
                        aria-label={__('Destination')}
                        type="text"
                        name="destination"
                        value={data.destination}
                        onChange={(e) => setData('destination', e.target.value)}
                        placeholder={__('e.g. DOCK-01')}
                        className={FIELD_CLS}
                    />
                    {errors.destination && <p className="mt-1 text-xs text-om-blocked">{errors.destination}</p>}
                </div>

                <div>
                    <div className="block text-[13px] font-medium text-om-muted mb-1">{__('Operator')}</div>
                    <Dropdown
                        aria-label={__('Operator')}
                        className="w-full"
                        value={data.worker_id == null ? '' : String(data.worker_id)}
                        onChange={(v) => setData('worker_id', v)}
                        placeholder={__('— None —')}
                        options={operators.map((op) => ({ value: String(op.id), label: `${op.code} — ${op.name}` }))}
                    />
                    {errors.worker_id && <p className="mt-1 text-xs text-om-blocked">{errors.worker_id}</p>}
                </div>

                <div>
                    <div className="block text-[13px] font-medium text-om-muted mb-1">{__('Notes')}</div>
                    <input
                        aria-label={__('Notes')}
                        type="text"
                        name="notes"
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        className={FIELD_CLS}
                    />
                    {errors.notes && <p className="mt-1 text-xs text-om-blocked">{errors.notes}</p>}
                </div>
            </div>

            <div className="mt-4">
                <Button type="submit" variant="primary" loading={processing} disabled={processing}>
                    {processing ? __('Saving…') : __('Update destination')}
                </Button>
            </div>
        </form>
    );
}

export default function LogisticsPallets() {
    const { statusLabels = {} } = usePage().props;

    const columns = [
        { key: 'pallet_no', label: __('Pallet number'), className: 'font-mono font-medium text-om-ink' },
        {
            key: 'status',
            label: __('Status'),
            // 'select', not `true`: DataTable renders a filter control only for
            // the literal 'text' / 'select' variants.
            filter: 'select',
            allLabel: __('All statuses'),
            options: Object.entries(statusLabels).map(([value, label]) => ({ value, label })),
            render: (r) => (
                <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_BADGE[r.status] ?? ''}`}>
                    {statusLabels[r.status] ?? r.status}
                </span>
            ),
        },
        {
            key: 'location',
            label: __('Location'),
            filter: 'text',
            className: 'font-mono text-om-ink',
            render: (r) => r.location || '—',
        },
        {
            key: 'destination',
            label: __('Destination'),
            filter: 'text',
            className: 'font-mono text-om-muted',
            render: (r) => r.destination || '—',
        },
        {
            key: 'transit',
            label: __('Transit'),
            // `transit` is derived, not a row field — without `value` the filter
            // (now on by default) would read undefined and match nothing.
            value: (r) => __(TRANSIT_LABELS[transitOf(r)]),
            sortAccessor: (r) => transitOf(r),
            render: (r) => {
                const state = transitOf(r);
                return (
                    <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${TRANSIT_BADGE[state]}`}>
                        {__(TRANSIT_LABELS[state])}
                    </span>
                );
            },
        },
        {
            key: 'arrived_at',
            label: __('Arrived'),
            className: 'text-om-muted text-xs',
            render: (r) => (r.arrived_at ? formatDateTime(r.arrived_at) : '—'),
        },
        { key: 'qty', label: __('Quantity') },
    ];

    // RowActions translates the label itself, so these stay raw English.
    const actions = () => [
        { label: 'Move', href: '/logistics/move-pallet', variant: 'secondary' },
    ];

    return (
        <>
            <Head title={__('Pallet Logistics')} />
            <RerouteCard />
            <ResourceTable
                shape="pallets"
                fullWidth
                title={__('Pallet Logistics')}
                columns={columns}
                orderBy="pallet_no"
                orderDir="desc"
                actions={actions}
                emptyText={__('No pallets yet.')}
            />
        </>
    );
}

LogisticsPallets.layout = (page) => <AppLayout>{page}</AppLayout>;
