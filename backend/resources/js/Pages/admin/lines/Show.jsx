import { useMemo, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Button, Checkbox, Dropdown, Icon, StatusPill } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import AppLayout from '../../../layouts/AppLayout';
import CustomFieldsDisplay from '../../../components/CustomFieldsDisplay';
import PageTrail from '../../../components/PageTrail';
import Tooltip from '../../../components/Tooltip';
import useConfirm from '../../../components/useConfirm';
import { hasLiveColumn, LiveClockProvider, useColumnDefs } from '../../../components/resourceColumns';
import { tableLabels } from '../../../lib/tableLabels';
import { __ } from '../../../lib/i18n';
import { woColumns } from '../work-orders/columns';

/**
 * Line configuration screen — design ref "OpenMES Line Detail.dc.html".
 *
 * Everything on this page is a setting, which is why it reads as a grid of
 * small cards rather than the record-then-history shape a work order gets. The
 * split is deliberate: the left column is what the line *is* (its kanban
 * statuses, its workstations), the right column is what operators *see* when
 * they stand at it, and the run history sits full-width underneath both.
 */

/** Machine state → the five tones StatusPill knows. */
const STATE_TONE = {
    RUNNING: 'running',
    IDLE: 'pending',
    SETUP: 'pending',
    STOPPED: 'blocked',
    FAULT: 'blocked',
    WAITING: 'downtime',
    CLEANING: 'downtime',
    MAINTENANCE: 'downtime',
};

/** Preset dot colours for a new line status — the Geist White state hues.
 *  Stored as data (a hex on the row), so these are literals, not tokens. */
const SWATCHES = ['#C9821E', '#EA5A2B', '#3E73C4', '#7A5FB0'];

const em = '—';

// ── Page furniture ────────────────────────────────────────────────────────────

/** A titled panel. `flush` drops the body padding for cards that hold a table. */
function Card({ title, count, subtitle, action, children, flush = false }) {
    return (
        <section className="overflow-hidden rounded-om border border-om-line bg-om-card">
            <div className="flex items-baseline justify-between gap-3 px-[22px] pt-4 pb-3">
                <div className="min-w-0">
                    <h2 className="text-[15px] font-semibold text-om-ink">
                        {title}
                        {count != null && (
                            <span className="ml-2 font-mono text-[11px] font-normal text-om-faint">{count}</span>
                        )}
                    </h2>
                    {subtitle && <p className="mt-1 text-[12.5px] text-om-muted">{subtitle}</p>}
                </div>
                {action}
            </div>
            <div className={flush ? '' : 'px-[22px] pb-5'}>{children}</div>
        </section>
    );
}

/**
 * One of our lists, inside a card.
 *
 * Takes the same declarative columns `ResourceTable` takes and renders them
 * through the same builder, so an embedded table is the list — same cells, same
 * filter row, same footer totals — minus the page shell a card can't host (its
 * heading lives in the card header, and its breadcrumb belongs to the page).
 * Chrome is off unless asked for: a four-row panel doesn't want a search box.
 */
function EmbeddedTable({ columns, searchable = false, columnToggle = false, paginated = false, ...props }) {
    const built = useColumnDefs(columns);

    // Same rule AppDataTable applies: a table with its chrome turned off is a
    // plain styled table, and a filter row over four rows is noise. Chrome on →
    // the columns keep the filters `buildColumnDefs` gave them.
    const defs = useMemo(
        () => (searchable ? built : built.map((d) => ({ ...d, meta: { ...d.meta, filter: undefined } }))),
        [built, searchable],
    );

    // DataTable directly, the way ResourceTable does it — AppDataTable's job is
    // to turn raw column config into a filtered table, and these defs are built
    // already.
    return (
        <LiveClockProvider active={hasLiveColumn(columns)}>
            <DataTable
                {...tableLabels()}
                columns={defs}
                searchable={searchable}
                columnToggle={columnToggle}
                paginated={paginated}
                // The card scrolls with the page; capping the body to the
                // viewport ("fill", the list default) would strand it.
                bodyMaxHeight="none"
                totalLabel={(n) => __(':count rows', { count: n })}
                {...props}
            />
        </LiveClockProvider>
    );
}

/** The micro-label above a form control. */
function FieldLabel({ children }) {
    return (
        <div className="mb-1.5 font-mono text-[9px] tracking-[0.1em] text-om-faint uppercase">{children}</div>
    );
}

/** One tile in the stat strip. */
function Stat({ href, label, value, icon, tint }) {
    const Wrapper = href ? Link : 'div';
    return (
        <Wrapper
            href={href}
            className={`flex items-center gap-3.5 rounded-om border border-om-line bg-om-card px-[18px] py-4 transition-colors ${
                href ? 'hover:border-om-faintest' : ''
            }`}
        >
            <div className="flex-1">
                <p className="mb-2 font-mono text-[9.5px] tracking-[0.1em] text-om-faint uppercase">{label}</p>
                <p className="font-mono text-[26px] leading-none font-medium tracking-[-0.02em] text-om-ink">{value}</p>
            </div>
            <span className={`flex size-9 shrink-0 items-center justify-center rounded-[10px] ${tint}`}>
                <Icon name={icon} size={17} />
            </span>
        </Wrapper>
    );
}

/** A dashed placeholder standing in for a list that is empty on purpose. */
function EmptyNote({ children }) {
    return (
        <div className="flex items-center gap-2.5 rounded-om-sm border border-dashed border-om-line px-3.5 py-3">
            <span className="size-2 shrink-0 rounded-full bg-om-faintest" />
            <span className="text-[12.5px] text-om-muted">{children}</span>
        </div>
    );
}

/** The ✕ that removes a chip or a row. */
function RemoveButton({ label, onClick, className = '' }) {
    return (
        <Tooltip label={label}>
            <button
                type="button"
                onClick={onClick}
                aria-label={label}
                className={`text-om-faint transition-colors hover:text-om-blocked ${className}`}
            >
                <Icon name="x" size={13} />
            </button>
        </Tooltip>
    );
}

function Header({ line, onToggleActive }) {
    return (
        <div className="mb-5 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-start">
            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-3">
                    <h1 className="text-[30px] leading-none font-semibold tracking-[-0.02em] text-om-ink">
                        {line.name}
                    </h1>
                    <span
                        className={`inline-flex items-center gap-1.5 rounded-[20px] px-[11px] py-1 font-mono text-[10px] tracking-[0.05em] uppercase ${
                            line.is_active ? 'bg-om-running-bg text-om-running' : 'bg-om-chip text-om-faint'
                        }`}
                    >
                        {line.is_active ? __('Active') : __('Inactive')}
                    </span>
                    <span className="rounded-[5px] border border-om-line px-2 py-0.5 font-mono text-[10.5px] tracking-[0.08em] text-om-faint">
                        {line.code}
                    </span>
                </div>
                {line.description && <p className="mt-1.5 text-[13.5px] text-om-muted">{line.description}</p>}
            </div>

            <div className="flex flex-wrap gap-2">
                <Link
                    href="/admin/lines"
                    className="inline-flex items-center gap-1.5 rounded-om-sm border border-om-line bg-om-card px-4 py-[9px] text-[13px] font-medium text-om-muted transition-colors hover:bg-om-chip"
                >
                    <Icon name="arrow-left" size={14} />
                    {__('Back')}
                </Link>
                <Link
                    href={`/admin/lines/${line.id}/edit`}
                    className="inline-flex items-center rounded-om-sm border border-om-line bg-om-card px-4 py-[9px] text-[13px] font-medium text-om-ink transition-colors hover:bg-om-chip"
                >
                    {__('Edit line')}
                </Link>
                <Button
                    type="button"
                    variant={line.is_active ? 'danger' : 'primary'}
                    onClick={onToggleActive}
                >
                    {line.is_active ? __('Deactivate') : __('Activate')}
                </Button>
            </div>
        </div>
    );
}

// ── Line statuses ─────────────────────────────────────────────────────────────

function LineStatusesCard({ line, lineStatuses }) {
    const form = useForm({ color: SWATCHES[0], name: '', sort_order: 10 });
    const { confirm, dialog } = useConfirm();

    const submit = (e) => {
        e.preventDefault();
        form.post(`/admin/lines/${line.id}/statuses`, { preserveScroll: true, onSuccess: () => form.reset() });
    };

    const deleteStatus = (statusId) => {
        confirm({ title: __('Delete this status?') }, () => {
            router.delete(`/admin/line-statuses/${statusId}`, { preserveScroll: true });
        });
    };

    const isPreset = SWATCHES.includes(form.data.color);

    return (
        <Card
            title={__('Line Statuses')}
            subtitle={__('Kanban statuses for work orders on this line. Global statuses are muted.')}
            action={
                <Link href="/admin/line-statuses" className="shrink-0 text-[12px] font-semibold text-om-accent hover:underline">
                    {__('Manage global statuses')} →
                </Link>
            }
        >
            <div className="mb-[18px] flex flex-wrap gap-2">
                {lineStatuses.length === 0 ? (
                    <EmptyNote>{__('No statuses yet — add one below or use a global status.')}</EmptyNote>
                ) : (
                    lineStatuses.map((status) => {
                        const global = status.line_id === null;
                        return (
                            <span
                                key={status.id}
                                className={`inline-flex items-center gap-[7px] rounded-[20px] px-[13px] py-1.5 text-[12.5px] font-medium ${
                                    global
                                        ? 'bg-om-chip text-om-muted'
                                        : 'border border-om-line bg-om-card text-om-ink'
                                }`}
                            >
                                <span
                                    aria-hidden
                                    className="size-2 shrink-0 rounded-full"
                                    style={{ backgroundColor: status.color }}
                                />
                                {status.name}
                                <span className="font-mono text-[8.5px] tracking-[0.04em] text-om-faint uppercase">
                                    {[status.is_default ? __('default') : null, global ? __('global') : null]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </span>
                                {!global && (
                                    <RemoveButton label={__('Delete')} onClick={() => deleteStatus(status.id)} />
                                )}
                            </span>
                        );
                    })
                )}
            </div>

            <form onSubmit={submit} className="flex flex-wrap items-end gap-2.5 border-t border-om-line2 pt-3.5">
                <div>
                    <FieldLabel>{__('Color')}</FieldLabel>
                    <div className="flex items-center gap-1.5">
                        {SWATCHES.map((c) => (
                            <button
                                key={c}
                                type="button"
                                aria-label={c}
                                onClick={() => form.setData('color', c)}
                                style={{ backgroundColor: c }}
                                className={`size-[26px] rounded-om-sm ${
                                    form.data.color === c ? 'ring-2 ring-om-ink ring-offset-2 ring-offset-om-card' : ''
                                }`}
                            />
                        ))}
                        <label
                            title={__('Custom color')}
                            style={isPreset ? undefined : { backgroundColor: form.data.color }}
                            className={`relative flex size-[26px] cursor-pointer items-center justify-center overflow-hidden rounded-om-sm ${
                                isPreset
                                    ? 'border-[1.5px] border-dashed border-om-faintest bg-om-panel text-om-faint'
                                    : 'text-white ring-2 ring-om-ink ring-offset-2 ring-offset-om-card'
                            }`}
                        >
                            <input
                                type="color"
                                value={form.data.color}
                                onChange={(e) => form.setData('color', e.target.value)}
                                className="absolute inset-0 size-full cursor-pointer opacity-0"
                            />
                            <Icon name="plus" size={12} />
                        </label>
                    </div>
                </div>
                <div className="min-w-[160px] flex-1">
                    <FieldLabel>{__('Status name (line-specific)')}</FieldLabel>
                    <input
                        type="text"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder={__('e.g. Waiting for parts')}
                        className="form-input min-h-0 w-full py-2.5 text-[13px]"
                        maxLength={100}
                        required
                    />
                </div>
                <div className="w-[76px]">
                    <FieldLabel>{__('Order')}</FieldLabel>
                    <input
                        type="number"
                        value={form.data.sort_order}
                        onChange={(e) => form.setData('sort_order', e.target.value)}
                        min={0}
                        className="form-input min-h-0 w-full py-2.5 text-[13px]"
                    />
                </div>
                <Button type="submit" variant="primary" loading={form.processing} leftIcon={<Icon name="plus" size={14} />}>
                    {__('Add to this line')}
                </Button>
            </form>
            {dialog}
        </Card>
    );
}

// ── Workstations ──────────────────────────────────────────────────────────────

function WorkstationsCard({ line, effectiveWorkstations }) {
    const virtual = effectiveWorkstations.some((ws) => ws.is_line_itself);

    // Declared in the same config form the admin lists use, so the cells get the
    // list's treatment (alignment, hairlines, footer slot) from one place.
    const columns = useMemo(() => [
        {
            key: 'name',
            label: __('Workstation'),
            className: 'font-medium text-om-ink',
            flex: true,
        },
        {
            key: 'code',
            label: __('Code'),
            className: 'font-mono text-om-muted',
        },
        {
            key: 'state',
            label: __('State'),
            value: (r) => r.state ?? '',
            render: (r) => (r.state
                ? <StatusPill status={STATE_TONE[r.state] ?? 'pending'} label={r.state} />
                : <span className="text-om-faintest">{em}</span>),
        },
        {
            key: 'operator',
            label: __('Operator'),
            align: 'right',
            value: (r) => (r.operators ?? []).join(', '),
            render: (r) => ((r.operators ?? []).length
                ? <span className="text-om-ink">{r.operators.join(', ')}</span>
                : <span className="text-om-faintest">{em}</span>),
        },
    ], []);

    return (
        <Card
            title={__('Workstations')}
            count={line.workstations_count}
            subtitle={virtual ? __('No workstations configured — line itself acts as a single workstation.') : undefined}
            action={
                <Link
                    href={`/admin/lines/${line.id}/workstations`}
                    className="shrink-0 rounded-om-sm border border-om-line px-3 py-1.5 text-[12.5px] font-medium text-om-ink transition-colors hover:bg-om-chip"
                >
                    {__('Manage')}
                </Link>
            }
            flush
        >
            <EmbeddedTable
                data={effectiveWorkstations}
                columns={columns}
                getRowId={(r, i) => String(r.id ?? `virtual-${i}`)}
                emptyLabel={__('No workstations on this line yet.')}
            />
        </Card>
    );
}
// ── Product types ─────────────────────────────────────────────────────────────

function ProductTypesCard({ line, allProductTypes, assignedTypeIds }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ product_type_ids: assignedTypeIds });

    const toggleType = (id) => {
        const current = form.data.product_type_ids;
        form.setData(
            'product_type_ids',
            current.includes(id) ? current.filter((x) => x !== id) : [...current, id],
        );
    };

    const submit = (e) => {
        e.preventDefault();
        form.post(`/admin/lines/${line.id}/product-types/sync`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Card
            title={__('Assigned Product Types')}
            subtitle={__('Product types that can be produced on this line.')}
        >
            {line.product_types.length === 0 ? (
                <EmptyNote>{__('No product types assigned — all types are allowed.')}</EmptyNote>
            ) : (
                <div className="flex flex-wrap gap-2">
                    {line.product_types.map((pt) => (
                        <span
                            key={pt.id}
                            className="inline-flex items-center gap-[7px] rounded-[20px] bg-om-chip px-3 py-1.5 text-[12.5px] font-medium text-om-ink"
                        >
                            {pt.name}
                            <span className="font-mono text-[9.5px] text-om-faint">{pt.code}</span>
                        </span>
                    ))}
                </div>
            )}

            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                className="mt-3.5 inline-flex items-center gap-1 text-[12.5px] font-semibold text-om-accent hover:underline"
            >
                <Icon name={open ? 'minus' : 'plus'} size={13} />
                {open ? __('Hide selector') : __('Change assignment')}
            </button>

            {open && (
                <form onSubmit={submit} className="mt-3.5 border-t border-om-line2 pt-3.5">
                    {allProductTypes.length === 0 ? (
                        <p className="text-[12.5px] text-om-muted">{__('No active product types defined yet.')}</p>
                    ) : (
                        <>
                            <div className="mb-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                {allProductTypes.map((pt) => {
                                    const checked = form.data.product_type_ids.includes(pt.id);
                                    return (
                                        // The whole row toggles, so the box carries no
                                        // handler of its own — its click just bubbles
                                        // here. A <label> wrapper would double-fire:
                                        // clicking the text forwards a synthetic click
                                        // to the box, which bubbles back up.
                                        <div
                                            key={pt.id}
                                            onClick={() => toggleType(pt.id)}
                                            className={`flex cursor-pointer items-center gap-2 rounded-om-sm border p-2.5 transition-colors ${
                                                checked ? 'border-om-accent bg-om-accent-bg' : 'border-om-line2 hover:border-om-line'
                                            }`}
                                        >
                                            <Checkbox checked={checked} aria-label={pt.name} />
                                            <div className="min-w-0">
                                                <p className="truncate text-[13px] font-medium text-om-ink">{pt.name}</p>
                                                <p className="font-mono text-[10px] text-om-faint">{pt.code}</p>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                            <p className="mb-3 text-[11px] text-om-faint">
                                {__('Leave all unchecked to allow all product types on this line.')}
                            </p>
                            <Button type="submit" variant="primary" loading={form.processing}>
                                {__('Save Assignment')}
                            </Button>
                        </>
                    )}
                </form>
            )}
        </Card>
    );
}

// ── Operators ─────────────────────────────────────────────────────────────────

function initialsOf(name) {
    return String(name ?? '')
        .split(/\s+/)
        .filter(Boolean)
        .map((w) => w[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
}

function OperatorsCard({ line, availableOperators }) {
    const form = useForm({ user_id: '' });
    const { confirm, dialog } = useConfirm();

    const submit = (e) => {
        e.preventDefault();
        form.post(`/admin/lines/${line.id}/assign-operator`, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const unassign = (userId, userName) => {
        confirm({ title: __('Remove :name from this line?', { name: userName }) }, () => {
            router.delete(`/admin/lines/${line.id}/unassign-operator/${userId}`, { preserveScroll: true });
        });
    };

    return (
        <Card title={__('Assigned Operators')} count={line.users.length}>
            {line.users.length === 0 ? (
                <EmptyNote>{__('No operators assigned yet')}</EmptyNote>
            ) : (
                <div>
                    {line.users.map((user, i) => (
                        <div
                            key={user.id}
                            className={`flex h-[52px] items-center gap-3 ${
                                i === line.users.length - 1 ? '' : 'border-b border-om-line2'
                            }`}
                        >
                            <span
                                aria-hidden
                                className="flex size-[30px] shrink-0 items-center justify-center rounded-full bg-om-chip text-[10.5px] font-semibold text-om-ink"
                            >
                                {initialsOf(user.name)}
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-[13px] font-semibold text-om-ink">{user.name}</p>
                                <p className="mt-px truncate font-mono text-[10px] text-om-faint">{user.username}</p>
                            </div>
                            <RemoveButton
                                label={__('Remove operator')}
                                onClick={() => unassign(user.id, user.name)}
                                className="p-1"
                            />
                        </div>
                    ))}
                </div>
            )}

            {availableOperators.length > 0 ? (
                <form onSubmit={submit} className="mt-3.5 flex items-end gap-2">
                    <Dropdown
                        className="min-w-0 flex-1"
                        aria-label={__('Assign New Operator')}
                        placeholder={__('Select an operator..')}
                        options={availableOperators.map((op) => ({
                            value: String(op.id),
                            label: `${op.name} · ${op.username}`,
                        }))}
                        value={form.data.user_id == null ? '' : String(form.data.user_id)}
                        onChange={(v) => form.setData('user_id', v)}
                    />
                    <Button
                        type="submit"
                        variant="primary"
                        disabled={form.processing || !form.data.user_id}
                        leftIcon={<Icon name="plus" size={14} />}
                    >
                        {__('Assign')}
                    </Button>
                </form>
            ) : (
                <p className="mt-3.5 text-[12.5px] text-om-muted">
                    {__('All available operators are already assigned to this line.')}
                </p>
            )}
            {form.errors.user_id && <p className="mt-1 text-[11px] text-om-blocked">{form.errors.user_id}</p>}
            {dialog}
        </Card>
    );
}

// ── Default operator view ─────────────────────────────────────────────────────

function DefaultViewCard({ line }) {
    const current = line.default_operator_view ?? 'queue';

    const opts = [
        {
            value: 'queue',
            label: __('Queue'),
            desc: __('Standard work order list with status, batches, priority and actions.'),
        },
        {
            value: 'workstation',
            label: __('Workstation'),
            desc: __('Flat production table with quantities, Z1/Z2 shift inputs and inline entry.'),
        },
    ];

    const choose = (value) => {
        router.post(`/admin/lines/${line.id}/default-view`, { default_operator_view: value }, { preserveScroll: true });
    };

    return (
        <Card
            title={__('Default Operator View')}
            subtitle={__('Which view operators see by default when they select this line.')}
        >
            <div className="flex flex-col gap-2.5">
                {opts.map((opt) => {
                    const on = current === opt.value;
                    return (
                        <label
                            key={opt.value}
                            className={`flex cursor-pointer items-start gap-3 rounded-om-sm border p-3.5 transition-colors ${
                                on ? 'border-om-ink bg-om-panel' : 'border-om-line hover:border-om-faintest'
                            }`}
                        >
                            <input
                                type="radio"
                                name="default_operator_view"
                                value={opt.value}
                                checked={on}
                                onChange={() => choose(opt.value)}
                                className="sr-only"
                            />
                            <span
                                aria-hidden
                                className={`mt-0.5 size-[15px] shrink-0 rounded-full ${
                                    on ? 'border-[5px] border-om-ink' : 'border-[1.5px] border-om-faintest'
                                }`}
                            />
                            <div className="min-w-0">
                                <p className="text-[13.5px] font-semibold text-om-ink">{opt.label}</p>
                                <p className="mt-0.5 text-[12px] leading-[1.4] text-om-muted">{opt.desc}</p>
                            </div>
                        </label>
                    );
                })}
            </div>
        </Card>
    );
}

// ── Workstation view template ─────────────────────────────────────────────────

function ViewTemplateCard({ line, allViewTemplates }) {
    const form = useForm({ view_template_id: line.view_template_id != null ? String(line.view_template_id) : '' });

    const submit = (e) => {
        e.preventDefault();
        form.post(`/admin/lines/${line.id}/view-template`, { preserveScroll: true });
    };

    return (
        <Card
            title={__('Workstation View')}
            subtitle={__('View template defining which columns operators see in the Workstation view for this line.')}
        >
            <form onSubmit={submit} className="flex items-end gap-2">
                <div className="min-w-0 flex-1">
                    <FieldLabel>{__('View Template')}</FieldLabel>
                    <Dropdown
                        className="w-full"
                        options={[
                            { value: '', label: __('— Default (no custom columns) —') },
                            ...allViewTemplates.map((tpl) => ({
                                value: String(tpl.id),
                                label: `${tpl.name} (${tpl.columns_count})`,
                            })),
                        ]}
                        value={form.data.view_template_id == null ? '' : String(form.data.view_template_id)}
                        onChange={(v) => form.setData('view_template_id', v)}
                    />
                </div>
                <Button type="submit" variant="primary" loading={form.processing}>
                    {__('Save')}
                </Button>
            </form>
            {allViewTemplates.length === 0 && (
                <p className="mt-3 text-[11px] text-om-faint">
                    {__('No templates created yet.')}{' '}
                    <Link href="/admin/view-templates/create" className="text-om-accent hover:underline">
                        {__('Create one')}
                    </Link>
                </p>
            )}
        </Card>
    );
}

// ── Workstation view columns ──────────────────────────────────────────────────

function ViewColumnsCard({ line, viewColumns }) {
    const [columns, setColumns] = useState(
        viewColumns.map((c) => ({ label: c.label, key: c.key, source: c.source })),
    );
    const [newLabel, setNewLabel] = useState('');
    const [newKey, setNewKey] = useState('');
    const [newSource, setNewSource] = useState('extra_data');
    const [processing, setProcessing] = useState(false);

    const add = () => {
        if (!newLabel || !newKey) return;
        setColumns((cols) => [...cols, { label: newLabel, key: newKey, source: newSource }]);
        setNewLabel('');
        setNewKey('');
        setNewSource('extra_data');
    };

    const remove = (i) => setColumns((cols) => cols.filter((_, idx) => idx !== i));

    // Order is what operators actually read left-to-right, so it stays editable
    // even though the chips are compact — the arrows live inside the chip.
    const move = (i, delta) => {
        setColumns((cols) => {
            const j = i + delta;
            if (j < 0 || j >= cols.length) return cols;
            const next = [...cols];
            [next[i], next[j]] = [next[j], next[i]];
            return next;
        });
    };

    const submit = (e) => {
        e.preventDefault();
        setProcessing(true);
        router.post(`/admin/lines/${line.id}/view-columns`, { columns }, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Card
            title={__('Workstation View Columns')}
            subtitle={__('Columns operators see in the Workstation view. extra_data pulls from imported data, field from order fields.')}
        >
            <form onSubmit={submit}>
                {columns.length === 0 ? (
                    <EmptyNote>{__('No custom columns — default view will be shown.')}</EmptyNote>
                ) : (
                    <div className="flex flex-wrap gap-1.5">
                        {columns.map((col, i) => (
                            <span
                                key={`${col.key}-${i}`}
                                className="inline-flex items-center gap-[7px] rounded-om-sm bg-om-chip px-2.5 py-1.5 text-[12px] font-medium text-om-ink"
                            >
                                <span className="flex items-center">
                                    <Tooltip label={__('Move left')}>
                                        <button
                                            type="button"
                                            onClick={() => move(i, -1)}
                                            disabled={i === 0}
                                            aria-label={__('Move left')}
                                            className="text-om-faint transition-colors hover:text-om-ink disabled:opacity-30"
                                        >
                                            <Icon name="chevron-left" size={13} />
                                        </button>
                                    </Tooltip>
                                    <Tooltip label={__('Move right')}>
                                        <button
                                            type="button"
                                            onClick={() => move(i, 1)}
                                            disabled={i === columns.length - 1}
                                            aria-label={__('Move right')}
                                            className="text-om-faint transition-colors hover:text-om-ink disabled:opacity-30"
                                        >
                                            <Icon name="chevron-right" size={13} />
                                        </button>
                                    </Tooltip>
                                </span>
                                {col.label}
                                <span className="font-mono text-[9.5px] text-om-faint">
                                    {col.key} · {col.source}
                                </span>
                                <RemoveButton label={__('Remove')} onClick={() => remove(i)} />
                            </span>
                        ))}
                    </div>
                )}

                <div className="mt-3.5 flex flex-wrap items-end gap-2">
                    <div className="min-w-[110px] flex-1">
                        <FieldLabel>{__('Label')}</FieldLabel>
                        <input
                            type="text"
                            value={newLabel}
                            onChange={(e) => setNewLabel(e.target.value)}
                            placeholder={__('e.g. Material')}
                            className="form-input min-h-0 w-full py-2.5 text-[13px]"
                        />
                    </div>
                    <div className="min-w-[110px] flex-1">
                        <FieldLabel>{__('Data Key')}</FieldLabel>
                        <input
                            type="text"
                            value={newKey}
                            onChange={(e) => setNewKey(e.target.value)}
                            placeholder={__('e.g. material')}
                            className="form-input min-h-0 w-full py-2.5 text-[13px]"
                        />
                    </div>
                    <div className="w-[140px]">
                        <FieldLabel>{__('Source')}</FieldLabel>
                        <Dropdown
                            className="w-full"
                            aria-label={__('Source')}
                            options={[
                                { value: 'extra_data', label: 'extra_data' },
                                { value: 'field', label: 'field' },
                            ]}
                            value={newSource}
                            onChange={(v) => setNewSource(v)}
                        />
                    </div>
                    <Button
                        type="button"
                        variant="primary"
                        onClick={add}
                        disabled={!newLabel || !newKey}
                        leftIcon={<Icon name="plus" size={14} />}
                    >
                        {__('Add')}
                    </Button>
                </div>

                <div className="mt-3 flex justify-end">
                    <Button type="submit" variant="ghost" loading={processing}>
                        {__('Save View Columns')}
                    </Button>
                </div>
            </form>
        </Card>
    );
}

// ── Recent work orders ────────────────────────────────────────────────────────

/**
 * What `LineManagementController::show()` puts in the `workOrders` prop, as
 * column keys. Add a field there and it can be added here; not before.
 */
const PANEL_COLUMNS = ['order_no', 'product', 'qty', 'status', 'priority', 'due_date', 'created_at', 'batches'];

function WorkOrdersCard({ line, workOrders, productTypeNames, batchCounts }) {
    // The work-order list's own columns, so this panel and /admin/work-orders
    // show an order the same way — the produced meter, the due countdown, the
    // age, the status badge.
    //
    // Narrowed to the nine fields the controller actually sends. `woColumns`
    // also carries Line, Customer and the whole optional set, and those are not
    // in this payload: switching one on from the Columns menu would draw "—" on
    // every row, sort on `undefined` and offer a filter over nothing.
    const columns = useMemo(
        () => woColumns({
            productTypeNames,
            counts: batchCounts,
            detailHref: (r) => `/admin/work-orders/${r.id}`,
        }).filter((c) => PANEL_COLUMNS.includes(c.key)),
        [productTypeNames, batchCounts],
    );

    const truncated = line.work_orders_count > workOrders.length;

    return (
        <Card
            title={__('Work Orders')}
            count={truncated
                ? __(':shown most recent of :total', { shown: workOrders.length, total: line.work_orders_count })
                : line.work_orders_count}
            action={
                <Link
                    href={`/admin/work-orders?line_id=${line.id}`}
                    className="shrink-0 text-[12px] font-semibold text-om-accent hover:underline"
                >
                    {__('Open all :count', { count: line.work_orders_count })} →
                </Link>
            }
            flush
        >
            <EmbeddedTable
                data={workOrders}
                columns={columns}
                getRowId={(r) => String(r.id)}
                onRowDoubleClick={(row) => router.visit(`/admin/work-orders/${row.id}`)}
                // Full list chrome: this panel holds enough orders to be worth
                // searching, filtering and totalling, which is the whole reason
                // it reuses the list's columns.
                searchable
                columnToggle
                paginated
                pageSize={10}
                emptyLabel={__('No work orders yet')}
            />
        </Card>
    );
}
// ── Page ──────────────────────────────────────────────────────────────────────

export default function LineShow() {
    const {
        line,
        workOrders = [],
        availableOperators = [],
        lineStatuses = [],
        allProductTypes = [],
        assignedTypeIds = [],
        viewColumns = [],
        allViewTemplates = [],
        effectiveWorkstations = [],
        customFields = [],
        productTypeNames = {},
        batchCounts = {},
    } = usePage().props;

    const toggleActive = () => {
        router.post(`/admin/lines/${line.id}/toggle-active`, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={`${line.name} — ${__('Configure')}`} />
            <PageTrail append={line.name} />

            {/* Full-bleed, like the lists: `main` is unpadded and the content
                starts at its edge, so the heading lines up with the breadcrumb
                above it. A centred max-width box indented the page against the
                header bar instead. */}
            <div className="w-full pb-10">
                <Header line={line} onToggleActive={toggleActive} />

                <div className="mb-4 grid grid-cols-1 gap-3.5 sm:grid-cols-3">
                    <Stat
                        href={`/admin/work-orders?line_id=${line.id}`}
                        label={__('Work Orders')}
                        value={line.work_orders_count}
                        icon="clipboard-list"
                        tint="bg-om-accent-bg text-om-accent"
                    />
                    <Stat
                        href={`/admin/lines/${line.id}/workstations`}
                        label={__('Workstations')}
                        value={line.workstations_count}
                        icon="factory"
                        tint="bg-om-running-bg text-om-running"
                    />
                    <Stat
                        label={__('Assigned Operators')}
                        value={line.users_count}
                        icon="users"
                        tint="bg-om-accepted-bg text-om-accepted"
                    />
                </div>

                <div className="grid grid-cols-1 items-start gap-4 xl:grid-cols-[1.5fr_1fr]">
                    {/* Left: the line's own make-up, plus the two column editors —
                        their three-input add rows need the wider track. */}
                    <div className="flex min-w-0 flex-col gap-4">
                        <LineStatusesCard line={line} lineStatuses={lineStatuses} />
                        <WorkstationsCard line={line} effectiveWorkstations={effectiveWorkstations} />
                        <ViewTemplateCard line={line} allViewTemplates={allViewTemplates} />
                        <ViewColumnsCard line={line} viewColumns={viewColumns} />
                    </div>

                    {/* Right: who and what the line is allowed to run. */}
                    <div className="flex min-w-0 flex-col gap-4">
                        <ProductTypesCard
                            line={line}
                            allProductTypes={allProductTypes}
                            assignedTypeIds={assignedTypeIds}
                        />
                        <OperatorsCard line={line} availableOperators={availableOperators} />
                        <DefaultViewCard line={line} />
                        <CustomFieldsDisplay definitions={customFields} values={line.custom_fields ?? {}} />
                    </div>
                </div>

                <div className="mt-4">
                    <WorkOrdersCard
                        line={line}
                        workOrders={workOrders}
                        productTypeNames={productTypeNames}
                        batchCounts={batchCounts}
                    />
                </div>
            </div>
        </>
    );
}

LineShow.layout = (page) => <AppLayout>{page}</AppLayout>;
