import { __ } from '../../../lib/i18n';

export const WO_STATUSES = ['PENDING', 'ACCEPTED', 'IN_PROGRESS', 'PAUSED', 'CHANGE_HOLD', 'BLOCKED', 'DONE', 'REJECTED', 'CANCELLED'];

/**
 * Tone + Lucide icon per work-order status, feeding `<StatusBadge>`.
 *
 * The tones spend green exactly once, on DONE. The old palette gave emerald to
 * IN_PROGRESS and green to DONE, so a list of running orders read as a list of
 * finished ones — the single most misleading thing the table did. Everything
 * that isn't "successfully finished" now gives up its claim to green:
 *
 *   IN_PROGRESS  purple — has to feel active and distinct, and purple carries no
 *                success/warning convention to be misread as.
 *   ACCEPTED     blue   — approved but not started: informational, not a result.
 *   PAUSED       amber  — someone chose to stop it (pause icon).
 *   BLOCKED      red    — something is stopping it (warning triangle). The icons
 *                carry the distinction, since the two are the closest pair here.
 *   REJECTED     deep red — a decision, not an obstacle.
 *   CANCELLED    hollow — PENDING and CANCELLED are both semantically grey, so
 *                rather than hunt for a ninth hue this one varies the treatment.
 *                An outlined chip also reads as "this row is inert".
 *
 * Every status keeps a distinct icon, so none of it depends on hue alone.
 */
export const WO_STATUS_META = {
    PENDING: { tone: 'neutral', icon: 'clock' },
    ACCEPTED: { tone: 'info', icon: 'thumbs-up' },
    IN_PROGRESS: { tone: 'active', icon: 'play' },
    PAUSED: { tone: 'warn', icon: 'pause' },
    // Amber, not grey: a change hold blocks production until a change is approved,
    // which is nearer to BLOCKED than to a coffee break (#182).
    CHANGE_HOLD: { tone: 'warn', icon: 'lock' },
    BLOCKED: { tone: 'danger', icon: 'triangle-alert' },
    DONE: { tone: 'success', icon: 'circle-check' },
    REJECTED: { tone: 'critical', icon: 'x' },
    CANCELLED: { tone: 'ghost', icon: 'slash' },
};

/** Props for `<StatusBadge>` from a status enum value. */
export function woStatusBadge(status) {
    const meta = WO_STATUS_META[status] ?? { tone: 'neutral' };
    return { ...meta, label: woStatusLabel(status) };
}

/** Localized display label for a work-order status enum value. */
export function woStatusLabel(status) {
    const labels = {
        PENDING: __('Pending'),
        ACCEPTED: __('Accepted'),
        IN_PROGRESS: __('In Progress'),
        PAUSED: __('Paused'),
        CHANGE_HOLD: __('Change hold'),
        BLOCKED: __('Blocked'),
        DONE: __('Done'),
        REJECTED: __('Rejected'),
        CANCELLED: __('Cancelled'),
    };
    return labels[status] ?? status;
}

/** Label for a BOM (process template) option in the multi-BOM picker. */
function bomLabel(t) {
    const inactive = t.is_active ? '' : ` (${__('inactive')})`;
    return `${t.name} v${t.version}${inactive}`;
}

export function woFields(lines, productTypes, { withStatus = false, customers = [], bomTemplates = [], bomLocked = false, productRevisions = [] } = {}) {
    const fields = [
        { name: 'order_no', label: __('Order No'), required: true },
        { name: 'customer_order_no', label: __('Customer Order No') },
        {
            name: 'customer_id', label: __('Customer'), type: 'select',
            options: [{ value: '', label: __('— None —') }, ...customers.map((c) => ({ value: String(c.id), label: c.name }))],
        },
        {
            name: 'line_id', label: __('Line'), type: 'select',
            options: [{ value: '', label: __('— None —') }, ...lines.map((l) => ({ value: String(l.id), label: l.name }))],
        },
        {
            name: 'product_type_id', label: __('Product Type'), type: 'select',
            options: [{ value: '', label: __('— None —') }, ...productTypes.map((p) => ({ value: String(p.id), label: p.name }))],
        },
    ];

    // Optional product revision (#180) — released revisions only, scoped to the
    // selected product type. Empty is fine (revision-less / legacy order).
    if (productRevisions.length) {
        fields.push({
            name: 'product_revision_id', label: __('Product Revision'), type: 'select',
            filterByField: 'product_type_id',
            options: [
                { value: '', label: __('— None —') },
                ...productRevisions.map((r) => ({ value: String(r.id), label: r.revision_code, group: r.product_type_id })),
            ],
            help: __('Only released revisions of the selected product type. Locked once production starts.'),
        });
    }

    // Optional multi-BOM picker: select one or more bills of materials (process
    // templates) for the chosen product type. Left empty, the order auto-uses the
    // single active BOM for its product type (unchanged single-BOM behaviour).
    // Scoped to the selected product type via filterByField; hidden once the
    // order's BOMs are locked by started production.
    if (bomTemplates.length && !bomLocked) {
        fields.push({
            name: 'bom_template_ids', label: __('Bills of Materials'), type: 'checkbox-group',
            filterByField: 'product_type_id',
            options: bomTemplates.map((t) => ({ value: t.id, label: bomLabel(t), group: t.product_type_id })),
            help: __('Select one or more BOMs. Requirements sum across the selected BOMs. Leave empty to auto-use the active BOM for the product type.'),
        });
    }

    fields.push(
        { name: 'planned_qty', label: __('Planned Qty'), type: 'number', required: true },
        { name: 'unit_price', label: __('Unit Price'), type: 'number', help: __('Price per produced unit. Adds to the customer\'s revenue when the order completes.') },
        {
            name: 'counting_source', label: __('Counting Source'), type: 'select',
            options: [
                { value: 'operator', label: __('Operator (manual)') },
                { value: 'machine', label: __('Machine (automatic)') },
                { value: 'both', label: __('Both (machine + operator)') },
            ],
            help: __('Where produced quantity comes from. Machine-counted orders are driven by machine counter signals and block manual operator entry.'),
        },
        { name: 'priority', label: __('Priority'), type: 'number', help: __('Auto-calculated from priority rules when any are active; otherwise set manually.') },
        { name: 'due_date', label: __('Due Date'), type: 'date' },
        { name: 'description', label: __('Description'), type: 'textarea' },
    );
    if (withStatus) {
        fields.push({ name: 'status', label: __('Status'), type: 'select', options: WO_STATUSES.map((s) => ({ value: s, label: woStatusLabel(s) })) });
    }
    return fields;
}
