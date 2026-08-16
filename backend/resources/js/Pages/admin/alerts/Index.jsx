import { useEffect, useMemo, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { useLiveQuery } from '@tanstack/react-db';
import { Breadcrumbs, Button, Modal, StatusBadge, StatusPill } from '@openmes/ui';

import AppLayout from '../../../layouts/AppLayout';
import AppDataTable from '../../../components/AppDataTable';
import DueCountdown from '../../../components/DueCountdown';
import PageTitle from '../../../components/PageTitle';
import useConfirm from '../../../components/useConfirm';
import usePrompt from '../../../components/usePrompt';
import { realtimeCollection } from '../../../lib/realtimeCollection';
import { useSyncedShape } from '../../../lib/useSyncedShape';
import { __, elapsed, formatDate, formatDateTime } from '../../../lib/i18n';
import { woStatusBadge, woStatusLabel } from '../work-orders/fields';

/**
 * Admin Alerts — joins five collections (issues, work orders, and the issue
 * type / line / user lookups). Everything rides the single Reverb WebSocket, so
 * the alert lists update live as blocking/overdue/blocked state changes.
 *
 * Every list is the shared `AppDataTable` (design §12) rather than the
 * hand-rolled cards this page used to draw: the same sortable headers, filter
 * row, search and pager as every other list in the app, so a triage screen
 * showing 48 issues can actually be narrowed instead of scrolled. The panels
 * keep their own toolbar heading and count chip through `toolbarStart`.
 */
const OPEN_STATUSES = ['OPEN', 'ACKNOWLEDGED'];
const TERMINAL_STATUSES = ['DONE', 'REJECTED', 'CANCELLED'];

/**
 * Rows per panel — the app's list default. Deliberately not conditional on the
 * layout: TanStack reads `pageSize` once, into its initial state, so a value
 * derived from data that is still arriving would be frozen at whatever the first
 * render guessed.
 */
const PANEL_PAGE_SIZE = 50;

/** Body height for a panel that can't fill the viewport (two rows of panels). */
const PANEL_BODY_HEIGHT = 520;

export default function AlertsIndex() {
    // Live: the transactional data the alerts derive from.
    const issuesC = useMemo(() => realtimeCollection('issues_all', (r) => r.id), []);
    const ordersC = useMemo(() => realtimeCollection('work_orders_all', (r) => r.id), []);
    const { data: issues = [] } = useLiveQuery((q) => q.from({ r: issuesC }));
    const { data: orders = [] } = useLiveQuery((q) => q.from({ r: ordersC }));

    // Lookup tables (names + is_blocking) — all live over the one Reverb socket.
    const { data: types = [] } = useSyncedShape('issue_types_all');
    const { data: lines = [] } = useSyncedShape('lines_all');
    const { data: users = [] } = useSyncedShape('users');

    const { confirm, dialog: confirmDialog } = useConfirm();
    const { prompt, dialog: promptDialog } = usePrompt();
    // The issue whose full report is open. A row can only carry its first line.
    const [detail, setDetail] = useState(null);

    const derived = useMemo(() => {
        const typeById = new Map(types.map((t) => [String(t.id), t]));
        const orderById = new Map(orders.map((o) => [String(o.id), o]));
        const lineById = new Map(lines.map((l) => [String(l.id), l]));
        const userById = new Map(users.map((u) => [String(u.id), u]));

        const openIssues = issues
            .filter((i) => OPEN_STATUSES.includes(i.status))
            .map((i) => ({
                ...i,
                type: i.issue_type_id != null ? typeById.get(String(i.issue_type_id)) : null,
                order: i.work_order_id != null ? orderById.get(String(i.work_order_id)) : null,
                reporter: i.reported_by_id != null ? userById.get(String(i.reported_by_id)) : null,
                // An issue raised from the floor carries `reported_at`; the
                // fallback is for rows written before that column existed.
                reportedAt: i.reported_at ?? i.created_at ?? null,
            }))
            .sort((a, b) => String(b.reportedAt ?? '').localeCompare(String(a.reportedAt ?? '')));

        // The flag arrives as a boolean or as 't'/'f' depending on the path the
        // row took to the browser (snapshot vs. broadcast delta).
        //
        // An issue whose type hasn't arrived yet counts as blocking. The two
        // collections load independently, and treating "type unknown" as
        // non-blocking dumped every open issue into the secondary panel for the
        // frame between them — the whole page rearranging itself on load.
        const isBlocking = (i) => !i.type || i.type.is_blocking === true || i.type.is_blocking === 't';
        const blockingIssues = openIssues.filter(isBlocking);
        const nonBlockingIssues = openIssues.filter((i) => !isBlocking(i));

        const todayStr = new Date().toISOString().slice(0, 10);
        const withLine = (o) => ({ ...o, line: o.line_id != null ? lineById.get(String(o.line_id)) : null });

        const overdueOrders = orders
            .filter((o) => o.due_date && String(o.due_date).slice(0, 10) < todayStr && !TERMINAL_STATUSES.includes(o.status))
            .map(withLine)
            .sort((a, b) => String(a.due_date).localeCompare(String(b.due_date)));

        const blockedOrders = orders
            .filter((o) => o.status === 'BLOCKED')
            .map(withLine)
            .sort((a, b) => String(b.updated_at ?? '').localeCompare(String(a.updated_at ?? '')));

        return { blockingIssues, nonBlockingIssues, overdueOrders, blockedOrders };
    }, [issues, types, orders, lines, users]);

    const { blockingIssues, nonBlockingIssues, overdueOrders, blockedOrders } = derived;
    const total = blockingIssues.length + nonBlockingIssues.length + overdueOrders.length + blockedOrders.length;

    // One request for the whole list rather than one per row — see
    // IssueManagementController::bulk.
    const acknowledgeAll = (rows) => {
        const ids = rows.filter((i) => i.status === 'OPEN').map((i) => i.id);
        if (ids.length === 0) return;

        confirm(
            {
                title: __('Acknowledge :count open issue(s)?', { count: ids.length }),
                body: __('Acknowledged issues stay on this list until they are resolved.'),
                confirmLabel: __('Acknowledge all'),
                destructive: false,
            },
            () => router.post('/admin/issues/bulk', { action: 'acknowledge', ids }, { preserveScroll: true }),
        );
    };

    const acknowledge = (issue) =>
        router.post(`/admin/issues/${issue.id}/acknowledge`, {}, { preserveScroll: true });

    // Same prompt the issues list uses, so "resolve" means the same thing and
    // records the same note wherever it is done from.
    const resolve = (issue) =>
        prompt(
            {
                title: __('Resolution notes:'),
                label: __('Notes'),
                required: false,
                confirmLabel: __('Resolve'),
                multiline: true,
            },
            (notes) => router.post(`/admin/issues/${issue.id}/resolve`, { resolution_notes: notes }, { preserveScroll: true }),
        );

    const openOrder = (wo) => router.visit(`/admin/work-orders/${wo.id}`);

    // Growing a table to the bottom of the viewport is only right when it is the
    // last thing on the page: side by side with its neighbour (below 2xl the
    // panels stack, so the second one would start below the fold) and with no
    // second row of panels under them.
    const sideBySide = useMediaQuery('(min-width: 96rem)');
    const hasSecondRow = nonBlockingIssues.length > 0 || blockedOrders.length > 0;
    const fillToBottom = sideBySide && !hasSecondRow;

    // Only the types actually on the board — a dropdown listing all 21 issue
    // types, most of which match nothing, is a list of dead ends.
    const typeOptions = useMemo(() => {
        const names = new Set(
            [...blockingIssues, ...nonBlockingIssues].map((i) => i.type?.name).filter(Boolean),
        );
        return [...names].sort().map((name) => ({ value: name, label: name }));
    }, [blockingIssues, nonBlockingIssues]);

    const issueCols = useMemo(
        () => issueColumns({ acknowledge, resolve, openDetail: setDetail, typeOptions }),
        [typeOptions],
    );
    const overdueCols = useMemo(() => overdueColumns(), []);
    const blockedCols = useMemo(() => blockedColumns(), []);

    return (
        <>
            <Head title={__('Alerts')} />

            {/* The trail shares the app header's bar with the clock; the count
                and the live dot ride along with it rather than costing the
                content area a heading row of their own. */}
            <PageTitle>
                <div className="flex min-w-0 items-center gap-3">
                    <Breadcrumbs
                        linkAs={Link}
                        items={[
                            { label: __('Dashboard'), href: '/admin/dashboard', icon: 'layout-dashboard' },
                            { label: __('Alerts'), icon: 'bell' },
                        ]}
                    />
                    {total > 0 && (
                        <span className="rounded-[20px] bg-om-blocked px-[9px] py-[2px] font-mono text-[11px] font-semibold text-white">
                            {total}
                        </span>
                    )}
                    <span className="flex shrink-0 items-center gap-[6px]">
                        <span className="size-[7px] animate-om-pulse rounded-full bg-om-running" />
                        <span className="font-mono text-[10px] tracking-[0.06em] text-om-running">{__('LIVE')}</span>
                    </span>
                </div>
            </PageTitle>

            <div className="mx-auto w-full">
                {total === 0 ? (
                    <AllClear />
                ) : (
                    <div className="grid grid-cols-1 items-start gap-4 2xl:grid-cols-[1.45fr_1fr]">
                        {/* LEFT: the issues someone has to act on. */}
                        <Panel
                            title={__('Blocking issues')}
                            count={blockingIssues.length}
                            tone="bg-om-blocked-bg text-om-blocked"
                            action={
                                blockingIssues.some((i) => i.status === 'OPEN') && (
                                    <Button variant="ghost" size="sm" onClick={() => acknowledgeAll(blockingIssues)}>
                                        {__('Acknowledge all')}
                                    </Button>
                                )
                            }
                            columns={issueCols}
                            rows={blockingIssues}
                            emptyText={__('No blocking issues.')}
                            fill={fillToBottom}
                        />

                        {/* RIGHT: the orders the schedule has already lost. */}
                        <Panel
                            title={__('Overdue orders')}
                            count={overdueOrders.length}
                            tone="bg-om-downtime-bg text-om-downtime"
                            action={
                                <Link
                                    href="/admin/work-orders?overdue=1"
                                    className="shrink-0 text-[12px] font-semibold text-om-accent hover:underline"
                                >
                                    {__('Open in work orders →')}
                                </Link>
                            }
                            columns={overdueCols}
                            rows={overdueOrders}
                            emptyText={__('No overdue orders.')}
                            onRowDoubleClick={openOrder}
                            fill={fillToBottom}
                        />

                        {/* The rest only appears when there is something in it —
                            an empty panel is a row of chrome saying nothing. */}
                        {nonBlockingIssues.length > 0 && (
                            <Panel
                                title={__('Other open issues')}
                                count={nonBlockingIssues.length}
                                tone="bg-om-chip text-om-muted"
                                action={
                                    nonBlockingIssues.some((i) => i.status === 'OPEN') && (
                                        <Button variant="ghost" size="sm" onClick={() => acknowledgeAll(nonBlockingIssues)}>
                                            {__('Acknowledge all')}
                                        </Button>
                                    )
                                }
                                columns={issueCols}
                                rows={nonBlockingIssues}
                                emptyText={__('No open issues.')}
                            />
                        )}

                        {blockedOrders.length > 0 && (
                            <Panel
                                title={__('Blocked orders')}
                                count={blockedOrders.length}
                                tone="bg-om-blocked-bg text-om-blocked"
                                columns={blockedCols}
                                rows={blockedOrders}
                                emptyText={__('No blocked orders.')}
                                onRowDoubleClick={openOrder}
                            />
                        )}
                    </div>
                )}
            </div>

            {detail && (
                <IssueDetail
                    issue={detail}
                    onClose={() => setDetail(null)}
                    onAcknowledge={() => { setDetail(null); acknowledge(detail); }}
                    onResolve={() => { setDetail(null); resolve(detail); }}
                />
            )}
            {confirmDialog}
            {promptDialog}
        </>
    );
}

AlertsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;

/** Whether a CSS media query currently matches, as state. */
function useMediaQuery(query) {
    const [matches, setMatches] = useState(() =>
        typeof window === 'undefined' ? false : window.matchMedia(query).matches,
    );

    useEffect(() => {
        const mq = window.matchMedia(query);
        const onChange = () => setMatches(mq.matches);
        onChange();
        mq.addEventListener('change', onChange);
        return () => mq.removeEventListener('change', onChange);
    }, [query]);

    return matches;
}

/**
 * One alert list: the shared table with a heading, a count chip and an optional
 * action in its own toolbar.
 *
 * `fill` grows the rows into the space left below them (AppDataTable's default),
 * so both panels end at the bottom of the viewport with their pagers on the same
 * line instead of stopping wherever ten rows happened to run out. It carries a
 * page size to match: a capped body only reaches the bottom edge if there are
 * enough rows to scroll inside it.
 *
 * It is off when a second row of panels exists — a table measuring to the bottom
 * of the viewport would then push everything under it off the screen.
 */
function Panel({ title, count, tone, action, columns, rows, emptyText, onRowDoubleClick, fill = false }) {
    return (
        <section className="min-w-0">
            <AppDataTable
                data={rows}
                columns={columns}
                toolbarStart={
                    <div className="flex shrink-0 items-center gap-2.5">
                        <h2 className="text-[15px] font-semibold text-om-ink">{title}</h2>
                        <span className={`rounded-[20px] px-[9px] py-[2px] font-mono text-[11px] font-semibold ${tone}`}>
                            {count}
                        </span>
                    </div>
                }
                toolbarEnd={action || undefined}
                columnToggle={false}
                pageSize={PANEL_PAGE_SIZE}
                bodyMaxHeight={fill ? 'fill' : PANEL_BODY_HEIGHT}
                emptyLabel={emptyText}
                // Double-click, not single: a row stays selectable text, and the
                // identifier in the first column is a link for one click.
                onRowDoubleClick={onRowDoubleClick}
            />
        </section>
    );
}

/** Columns for either issue list — the same table, two different feeds. */
function issueColumns({ acknowledge, resolve, openDetail, typeOptions }) {
    return [
        {
            id: 'issue',
            accessorFn: (i) => i.title ?? i.description ?? '',
            header: __('Issue'),
            // The design's "All types" dropdown, on the column the type is
            // printed in — a column of its own cost 180px the panel doesn't
            // have, and pushed the action buttons off the right edge.
            meta: { flex: true, filter: 'select', options: typeOptions, allLabel: __('All types') },
            // The filter asks about the type; the column's value is the title,
            // so it needs its own matcher (a select hands back `{ eq }`).
            filterFn: (row, id, value) => {
                const want = value && typeof value === 'object' && 'eq' in value ? value.eq : value;
                if (want == null || want === '') return true;
                return (row.original.type?.name ?? '') === String(want);
            },
            cell: ({ row }) => {
                const issue = row.original;
                const open = issue.status === 'OPEN';
                return (
                    <div className="flex items-start gap-2.5">
                        {/* The dot is the row's own status, so it is legible before
                            you have read a word of it — pulsing while nobody has
                            picked the issue up, still once someone has. */}
                        <span
                            aria-hidden="true"
                            className={`mt-[5px] size-[9px] shrink-0 rounded-full ${
                                open ? 'animate-om-pulse bg-om-blocked' : 'bg-om-faintest'
                            }`}
                        />
                        <div className="min-w-0 max-w-[260px]">
                            <div className="flex items-center gap-2">
                                {/* The row can only show the first line of a
                                    report; the title opens the rest of it. */}
                                <button
                                    type="button"
                                    onClick={() => openDetail(issue)}
                                    className="min-w-0 cursor-pointer truncate text-left font-semibold text-om-ink hover:underline"
                                >
                                    {issue.title ?? issue.type?.name ?? __('Issue')}
                                </button>
                                {/* The status enum, not the verb: `__('Open')` is
                                    the button label ("Otwórz" — *open it*), which
                                    read as an action sitting in the title. */}
                                <StatusPill
                                    status={open ? 'blocked' : 'downtime'}
                                    pulse={false}
                                    label={__(issue.status)}
                                />
                            </div>
                            <div className="truncate text-[12px] text-om-muted">
                                {issue.type?.name && (
                                    <span className="text-om-faint">{issue.type.name} · </span>
                                )}
                                {issue.description}
                            </div>
                        </div>
                    </div>
                );
            },
        },
        {
            id: 'work_order',
            accessorFn: (i) => i.order?.order_no ?? '',
            header: __('Work order'),
            cell: ({ row }) => {
                const order = row.original.order;
                return order ? (
                    <Link
                        href={`/admin/work-orders/${order.id}`}
                        className="font-mono text-[12px] font-medium text-om-accent hover:underline"
                    >
                        {order.order_no}
                    </Link>
                ) : (
                    <span className="text-om-faint">—</span>
                );
            },
        },
        {
            id: 'reported_by',
            accessorFn: (i) => i.reporter?.name ?? '',
            header: __('Reported by'),
            cell: ({ row }) => <span className="text-om-muted">{row.original.reporter?.name ?? '—'}</span>,
        },
        {
            id: 'age',
            accessorFn: (i) => i.reportedAt ?? '',
            header: __('Age'),
            // The cell shows a duration and the value is a timestamp, so a filter
            // on it would be asking about something the reader can't see.
            meta: { align: 'right', filter: false },
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-om-muted">{elapsed(row.original.reportedAt)}</span>
            ),
        },
        {
            id: '_actions',
            header: __('Actions'),
            enableSorting: false,
            enableHiding: false,
            meta: { align: 'right', filter: false },
            cell: ({ row }) => {
                const issue = row.original;
                return (
                    <div className="flex items-center justify-end gap-1.5">
                        {issue.status === 'OPEN' && (
                            <Button variant="ghost" size="sm" onClick={() => acknowledge(issue)}>
                                {__('Ack')}
                            </Button>
                        )}
                        <Button variant="ghost" size="sm" onClick={() => resolve(issue)}>
                            {__('Resolve')}
                        </Button>
                    </div>
                );
            },
        },
    ];
}

/** Order + line, shared by the two work-order panels. */
function orderIdentityColumns() {
    return [
        {
            id: 'order',
            accessorKey: 'order_no',
            header: __('Order'),
            cell: ({ row }) => {
                const wo = row.original;
                return (
                    <div>
                        <Link
                            href={`/admin/work-orders/${wo.id}`}
                            className="font-mono text-[12px] font-medium text-om-ink hover:text-om-accent hover:underline"
                        >
                            {wo.order_no}
                        </Link>
                        {wo.due_date && (
                            <div className="font-mono text-[10px] text-om-faint">
                                {__('due :date', { date: fmtDate(wo.due_date) })}
                            </div>
                        )}
                    </div>
                );
            },
        },
        {
            id: 'line',
            accessorFn: (wo) => wo.line?.name ?? '',
            header: __('Line'),
            meta: { flex: true, allLabel: __('All lines') },
            cell: ({ row }) => <span className="text-om-muted">{row.original.line?.name ?? '—'}</span>,
        },
    ];
}

function overdueColumns() {
    return [
        ...orderIdentityColumns(),
        {
            id: 'overdue',
            accessorFn: (wo) => wo.due_date ?? '',
            header: __('Overdue by'),
            meta: { align: 'right', filter: false },
            // "2 days ago" is how you describe an event; a deadline wants the
            // size of the overrun, in the same units every other due date on
            // the site is measured in.
            cell: ({ row }) => (
                <DueCountdown due={row.original.due_date} className="font-mono text-[12px] font-semibold" />
            ),
        },
        {
            id: 'status',
            accessorKey: 'status',
            header: __('Status'),
            meta: { optionLabel: woStatusLabel, allLabel: __('All statuses') },
            cell: ({ row }) => <StatusBadge size="sm" {...woStatusBadge(row.original.status)} />,
        },
    ];
}

function blockedColumns() {
    return [
        ...orderIdentityColumns(),
        {
            id: 'blocked_since',
            accessorFn: (wo) => wo.updated_at ?? '',
            header: __('Blocked since'),
            meta: { align: 'right', filter: false },
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-om-muted">{elapsed(row.original.updated_at)}</span>
            ),
        },
    ];
}

/**
 * The whole report behind a row.
 *
 * A row shows one truncated line of the description, which is the sentence that
 * says what actually happened — "which piece, on which check, by how much". This
 * is where that is readable, with the row's two actions on the same screen so
 * reading it and acting on it aren't in different places.
 */
function IssueDetail({ issue, onClose, onAcknowledge, onResolve }) {
    const open = issue.status === 'OPEN';

    return (
        <Modal
            open
            onClose={onClose}
            title={issue.title ?? issue.type?.name ?? __('Issue')}
            subtitle={issue.type?.name}
            closeLabel={__('Close')}
            className="max-w-[560px]"
            footer={
                <>
                    <Link href="/admin/issues" className="mr-auto text-[12.5px] font-semibold text-om-accent hover:underline">
                        {__('Open in issues →')}
                    </Link>
                    {open && <Button variant="secondary" onClick={onAcknowledge}>{__('Acknowledge')}</Button>}
                    <Button variant="primary" onClick={onResolve}>{__('Resolve')}</Button>
                </>
            }
        >
            <div className="space-y-4">
                <StatusPill status={open ? 'blocked' : 'downtime'} pulse={false} label={__(issue.status)} />

                {issue.description && (
                    <p className="text-[13px] leading-relaxed whitespace-pre-line text-om-ink">{issue.description}</p>
                )}

                <dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 border-t border-om-line2 pt-4 text-[12.5px]">
                    <Field label={__('Work order')}>
                        {issue.order ? (
                            <Link href={`/admin/work-orders/${issue.order.id}`} className="font-mono text-om-accent hover:underline">
                                {issue.order.order_no}
                            </Link>
                        ) : '—'}
                    </Field>
                    <Field label={__('Reported by')}>{issue.reporter?.name ?? '—'}</Field>
                    <Field label={__('Reported')}>
                        {issue.reportedAt ? `${formatDateTime(issue.reportedAt)} · ${elapsed(issue.reportedAt)}` : '—'}
                    </Field>
                    {issue.acknowledged_at && (
                        <Field label={__('Acknowledged')}>{formatDateTime(issue.acknowledged_at)}</Field>
                    )}
                </dl>
            </div>
        </Modal>
    );
}

function Field({ label, children }) {
    return (
        <>
            <dt className="text-om-muted">{label}</dt>
            <dd className="text-om-ink">{children}</dd>
        </>
    );
}

function AllClear() {
    return (
        <div className="flex flex-col items-center rounded-om border border-om-line bg-om-card py-16 text-center">
            <div className="mb-4 flex size-14 items-center justify-center rounded-full bg-om-running-bg">
                <svg className="size-7 text-om-running" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <p className="text-[17px] font-semibold text-om-ink">{__('All clear')}</p>
            <p className="mt-1 text-[13px] text-om-muted">{__('No active alerts at this time.')}</p>
        </div>
    );
}

function fmtDate(d) {
    if (!d) return '';
    const dt = new Date(d);
    return Number.isNaN(dt.getTime()) ? '' : formatDate(dt, { day: '2-digit', month: 'short' });
}
