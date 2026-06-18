/**
 * Design-system gallery — local-only (/dev/components) visual reference for
 * @openmes/ui, mirroring design/openmes-fable-remix "OpenMES Components".
 * Specimen strings below are sample data on purpose (not product UI — no i18n).
 */
import { useState } from 'react';
import {
    ActionMenu,
    Badge,
    Button,
    Checkbox,
    Calendar,
    ConfirmDialog,
    DatePicker,
    Dropdown,
    IconButton,
    InlineAlert,
    Modal,
    OnlineDot,
    ProgressBar,
    QuantityStepper,
    RadioGroup,
    SegmentedControl,
    Skeleton,
    StatusPill,
    Switch,
    Tabs,
    TextField,
    ToastProvider,
    useToast,
} from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

const SAMPLE_ROWS = [
    { id: 'WO-2026-001', product: 'HEPA-13 Standard', status: 'running', prio: 'H', plan: 250, made: 108 },
    { id: 'WO-2026-002', product: 'Carbon Pre-Filter', status: 'running', prio: 'M', plan: 400, made: 320 },
    { id: 'WO-2026-003', product: 'HEPA-13 Standard', status: 'done', prio: 'M', plan: 150, made: 150 },
    { id: 'WO-2026-004', product: 'HEPA Slim', status: 'done', prio: 'L', plan: 200, made: 200 },
    { id: 'WO-2026-005', product: 'HEPA Slim', status: 'pending', prio: 'H', plan: 120, made: 0 },
    { id: 'WO-2026-006', product: 'Carbon Pre-Filter', status: 'blocked', prio: 'H', plan: 300, made: 60 },
    { id: 'WO-2026-007', product: 'Polo Logo', status: 'pending', prio: 'L', plan: 140, made: 0 },
    { id: 'WO-2026-008', product: 'HEPA-13 Pro', status: 'pending', prio: 'M', plan: 180, made: 0 },
    { id: 'WO-2026-009', product: 'Carbon Deep-Bed', status: 'blocked', prio: 'H', plan: 220, made: 40 },
    { id: 'WO-2026-010', product: 'HEPA Slim', status: 'running', prio: 'M', plan: 160, made: 96 },
];

const TABLE_COLUMNS = [
    {
        accessorKey: 'id',
        header: 'Order',
        size: 130,
        meta: { filter: 'text', filterPlaceholder: 'Filter…' },
        cell: (info) => <span className="font-mono text-[12px] font-medium text-om-ink">{info.getValue()}</span>,
    },
    {
        accessorKey: 'product',
        header: 'Product',
        meta: { filter: 'text', filterPlaceholder: 'Filter product…', flex: true },
        cell: (info) => <span className="text-[13.5px] font-medium text-om-ink">{info.getValue()}</span>,
    },
    {
        accessorKey: 'status',
        header: 'Status',
        size: 116,
        meta: { filter: 'select', allLabel: 'All status', options: ['running', 'pending', 'blocked', 'done'] },
        cell: (info) => <StatusPill status={info.getValue()} label={info.getValue().toUpperCase()} />,
    },
    {
        accessorKey: 'prio',
        header: 'Pri',
        size: 60,
        meta: { filter: 'select', allLabel: 'All', options: ['H', 'M', 'L'] },
        cell: (info) => (
            <span className={`font-mono text-[12px] ${info.getValue() === 'H' ? 'text-om-blocked' : info.getValue() === 'M' ? 'text-om-muted' : 'text-om-faint'}`}>
                {info.getValue()}
            </span>
        ),
    },
    { accessorKey: 'plan', header: 'Plan', size: 70, meta: { align: 'right' }, cell: (info) => <span className="font-mono text-[13px] text-om-ink">{info.getValue()}</span> },
    { accessorKey: 'made', header: 'Made', size: 70, meta: { align: 'right' }, cell: (info) => <span className="font-mono text-[13px] text-om-muted">{info.getValue()}</span> },
    {
        id: 'remain',
        header: 'Remain',
        size: 80,
        meta: { align: 'right' },
        accessorFn: (row) => row.plan - row.made,
        cell: (info) => (
            <span className={`font-mono text-[13px] font-semibold ${info.getValue() === 0 ? 'text-om-running' : 'text-om-accent'}`}>{info.getValue()}</span>
        ),
    },
];

function Section({ label, children, cols = false }) {
    return (
        <div className="mt-[18px] rounded-om border border-om-line bg-om-card px-[22px] py-5">
            <div className="mb-4 font-mono text-[10px] uppercase tracking-[0.12em] text-om-faint">{label}</div>
            <div className={cols ? 'flex flex-wrap items-start gap-9' : ''}>{children}</div>
        </div>
    );
}

function GalleryBody() {
    const toast = useToast();
    const [switches, setSwitches] = useState({ a: true, b: false });
    const [checked, setChecked] = useState(true);
    const [lot, setLot] = useState('26-0512-A');
    const [notes, setNotes] = useState('');
    const [qty, setQty] = useState(250);
    const [size, setSize] = useState('small');
    const [mode, setMode] = useState('batch');
    const [tab, setTab] = useState('details');
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [modalOpen, setModalOpen] = useState(false);
    const [status, setStatus] = useState('all');
    const [lines, setLines] = useState(['WSZ-01', 'WSZ-02']);
    const [due, setDue] = useState('2026-05-26');
    const [day, setDay] = useState('2026-05-26');

    return (
        <div className="min-h-screen bg-[#E9E8E3] p-10 font-sans">
            <div className="mx-auto max-w-[1280px]">
                <div className="mb-2 flex items-end justify-between gap-6">
                    <div>
                        <div className="mb-4 flex items-center gap-2.5">
                            <img src="/logo_open_mes.png" alt="OpenMES" className="h-6 w-auto" />
                            <span className="rounded-[5px] border border-om-line px-[7px] py-0.5 font-mono text-[9.5px] text-om-faint">design system</span>
                        </div>
                        <h1 className="text-[32px] font-semibold tracking-[-0.025em] text-om-ink">Component gallery</h1>
                        <p className="mt-2 max-w-[560px] text-sm leading-normal text-om-muted">
                            Live @openmes/ui components — dev reference, mirrors the design handoff sheet.
                        </p>
                    </div>
                    <div className="text-right font-mono text-[10px] leading-[1.8] text-om-faint">
                        <div>GEIST · GEIST MONO</div>
                        <div>ACCENT #EA5A2B</div>
                        <div>RADIUS 12 / 8</div>
                    </div>
                </div>

                <Section label="02 — Buttons">
                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="primary">Primary</Button>
                        <Button variant="accent">Accent</Button>
                        <Button variant="secondary">Secondary</Button>
                        <Button variant="ghost">Ghost</Button>
                        <Button variant="danger">Danger</Button>
                        <Button variant="secondary" disabled>Disabled</Button>
                        <Button variant="secondary" loading>Saving…</Button>
                        <IconButton variant="primary">+</IconButton>
                        <IconButton variant="danger">!</IconButton>
                        <IconButton>⋯</IconButton>
                    </div>
                </Section>

                <Section label="03 — Switches & checkbox" cols>
                    <Switch checked={switches.a} onChange={(v) => setSwitches((s) => ({ ...s, a: v }))} />
                    <Switch checked={switches.b} onChange={(v) => setSwitches((s) => ({ ...s, b: v }))} />
                    <Switch checked disabled />
                    <Checkbox checked={checked} onChange={setChecked} label="Checked" />
                    <Checkbox checked={false} onChange={() => {}} label="Unchecked" />
                </Section>

                <Section label="04 — Inputs" cols>
                    <div className="w-60"><TextField label="Lot number" mono value={lot} onChange={setLot} /></div>
                    <div className="w-32"><QuantityStepper value={qty} onChange={setQty} min={0} /></div>
                    <div className="w-60"><TextField label="Notes" multiline value={notes} onChange={setNotes} placeholder="Typing…" /></div>
                    <div className="w-60"><TextField label="With error" value="" onChange={() => {}} error="Required field" /></div>
                </Section>

                <Section label="05 — Selection & tabs" cols>
                    <div className="w-72">
                        <SegmentedControl
                            options={[{ value: 'small', label: 'Small' }, { value: 'medium', label: 'Medium' }, { value: 'large', label: 'Large' }]}
                            value={size}
                            onChange={setSize}
                        />
                    </div>
                    <RadioGroup
                        options={[{ value: 'batch', label: 'Per batch' }, { value: 'shift', label: 'Per shift' }]}
                        value={mode}
                        onChange={setMode}
                    />
                    <div className="w-72">
                        <Tabs
                            tabs={[{ value: 'details', label: 'Details' }, { value: 'routing', label: 'Routing' }, { value: 'history', label: 'History' }]}
                            value={tab}
                            onChange={setTab}
                        />
                    </div>
                </Section>

                <Section label="06 — Status & badges" cols>
                    <StatusPill status="running" label="RUNNING" />
                    <StatusPill status="pending" label="PENDING" />
                    <StatusPill status="blocked" label="BLOCKED" />
                    <StatusPill status="downtime" label="DOWNTIME" />
                    <StatusPill status="done" label="DONE" />
                    <Badge variant="danger">3</Badge>
                    <Badge variant="neutral">12</Badge>
                    <Badge variant="outline">HIGH</Badge>
                    <OnlineDot label="ONLINE" />
                    <div className="w-64"><ProgressBar value={43} /></div>
                </Section>

                <Section label="07 — Action menu / 13 — Dropdowns" cols>
                    <ActionMenu
                        trigger={<Button variant="ghost">Actions ⋯</Button>}
                        items={[
                            { key: 'edit', label: 'Edit order', onSelect: () => {} },
                            { key: 'dup', label: 'Duplicate', onSelect: () => {} },
                            { key: 'print', label: 'Print label', onSelect: () => {} },
                            { divider: true },
                            { key: 'del', label: 'Delete', destructive: true, onSelect: () => {} },
                        ]}
                    />
                    <div className="w-60">
                        <Dropdown
                            options={[{ value: 'all', label: 'All status' }, { value: 'running', label: 'Running' }, { value: 'pending', label: 'Pending' }, { value: 'blocked', label: 'Blocked' }, { value: 'done', label: 'Done' }]}
                            value={status}
                            onChange={setStatus}
                        />
                    </div>
                    <div className="w-60">
                        <Dropdown
                            multiple
                            options={['WSZ-01', 'WSZ-02', 'WSP-01', 'QA-01'].map((v) => ({ value: v, label: v }))}
                            values={lines}
                            onChange={setLines}
                            label={lines.length === 4 ? 'All lines' : `${lines.length} lines selected`}
                        />
                    </div>
                </Section>

                <Section label="14 — Date picker" cols>
                    <div className="w-60"><DatePicker label="Due date" value={due} onChange={setDue} /></div>
                    <Calendar value={day} onChange={setDay} />
                </Section>

                <Section label="15 — Skeleton" cols>
                    <div className="flex w-64 items-center gap-3">
                        <Skeleton circle height={40} />
                        <div className="flex-1 space-y-2">
                            <Skeleton width="70%" height={13} />
                            <Skeleton width="45%" height={11} />
                        </div>
                    </div>
                </Section>

                <Section label="08 — Inline alerts">
                    <div className="grid grid-cols-4 gap-3">
                        <InlineAlert severity="success" title="Batch #3 closed">108 pcs accepted · LOT printed.</InlineAlert>
                        <InlineAlert severity="info" title="New work order">WO-2026-008 added to queue.</InlineAlert>
                        <InlineAlert severity="warning" title="Material low">Filter media at 12% — reorder.</InlineAlert>
                        <InlineAlert severity="error" title="Production blocked">Media tear — WO-2026-006 halted.</InlineAlert>
                    </div>
                </Section>

                <Section label="09 — Overlays" cols>
                    <Button variant="secondary" onClick={() => toast({ severity: 'success', title: 'Output saved', body: '+12 pcs logged to WO-2026-001' })}>
                        Success toast
                    </Button>
                    <Button variant="secondary" onClick={() => toast({ severity: 'warning', title: 'Downtime started', body: 'Reason — glue station warm-up' })}>
                        Warning toast
                    </Button>
                    <Button variant="secondary" onClick={() => toast({ severity: 'error', title: 'Sync failed', body: 'Offline — retrying in 5s…' })}>
                        Error toast
                    </Button>
                    <Button variant="danger" onClick={() => setConfirmOpen(true)}>Confirm dialog</Button>
                    <Button variant="primary" onClick={() => setModalOpen(true)}>Form modal</Button>
                </Section>

                <Section label="12 — Data table">
                    <DataTable
                        data={SAMPLE_ROWS}
                        columns={TABLE_COLUMNS}
                        fluid={false}
                        searchPlaceholder="Search all columns"
                        enableSelection
                        selectionLabel={(n, m) => `${n} of ${m} row(s) selected`}
                        bulkActions={(rows, clear) => (
                            <>
                                <Button variant="secondary" onClick={() => {}}>Print labels</Button>
                                <Button variant="danger" onClick={() => {}}>Mark blocked</Button>
                                <button type="button" className="cursor-pointer text-[12.5px] text-om-muted" onClick={clear}>Clear</button>
                            </>
                        )}
                        columnsLabel="Columns ▾"
                        columnsMenuLabel="Toggle columns"
                        emptyLabel="No rows match the filters."
                        rangeLabel={(start, end, total) => (total === 0 ? '0 results' : `${start}–${end} of ${total}`)}
                        pageSize={6}
                        bodyMaxHeight={252}
                    />
                </Section>

                <div className="mt-6 font-mono text-[11px] text-om-faint">$ openmes design-system · @openmes/ui · Geist White</div>
            </div>

            <ConfirmDialog
                open={confirmOpen}
                onClose={() => setConfirmOpen(false)}
                onConfirm={() => setConfirmOpen(false)}
                title="Delete WO-2026-007?"
                confirmLabel="Delete order"
                cancelLabel="Cancel"
            >
                This permanently removes the work order and its routing. Logged output stays in reports. Cannot be undone.
            </ConfirmDialog>

            <Modal
                open={modalOpen}
                onClose={() => setModalOpen(false)}
                title="New work order"
                subtitle="LINE WSZ-01"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setModalOpen(false)}>Cancel</Button>
                        <Button variant="primary" onClick={() => setModalOpen(false)}>Create</Button>
                    </>
                }
            >
                <div className="flex flex-col gap-3">
                    <Dropdown
                        options={[{ value: 'hepa13', label: 'HEPA-13 Standard' }, { value: 'carbon', label: 'Carbon Pre-Filter' }]}
                        value="hepa13"
                        onChange={() => {}}
                    />
                    <div className="flex gap-2.5">
                        <div className="flex-1"><TextField label="Qty" mono value="250" onChange={() => {}} /></div>
                        <div className="flex-1"><TextField label="Due" mono value="26 May" onChange={() => {}} /></div>
                    </div>
                </div>
            </Modal>
        </div>
    );
}

export default function ComponentGallery() {
    return (
        <ToastProvider>
            <GalleryBody />
        </ToastProvider>
    );
}
