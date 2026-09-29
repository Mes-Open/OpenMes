import { useEffect, useRef, useState, Fragment } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { Button, Checkbox, Dropdown } from '@openmes/ui';
import { __ } from '../../../lib/i18n';

const RESET_PERIODS = [
    { value: 'none', label: 'No reset' },
    { value: 'yearly', label: 'Yearly' },
    { value: 'monthly', label: 'Monthly' },
    { value: 'daily', label: 'Daily' },
    { value: 'hourly', label: 'Hourly' },
];

/**
 * Blank/loaded form values — the one builder Create, Edit and the list drawer
 * all share, so a new field can't be missed on one of them. The drawer call
 * site adds `stay: 1` itself (the standalone pages redirect, so they must not).
 */
export function lotSequenceInitial(r) {
    return {
        name: r?.name ?? '',
        product_type_id: r?.product_type_id != null ? String(r.product_type_id) : '',
        purpose: r?.purpose ?? 'lot',
        pattern: r?.pattern ?? '',
        prefix: r?.prefix ?? '',
        suffix: r?.suffix ?? '',
        pad_size: r?.pad_size ?? 4,
        year_prefix: !!r?.year_prefix,
        reset_period: r?.reset_period ?? 'none',
    };
}

/**
 * Create/edit form for LOT sequences with two modes:
 *  - Pattern: token template ("test-[date]-[seq]-[hour]") with a clickable
 *    token palette and a debounced live preview from the server.
 *  - Simple (legacy): prefix / year prefix / suffix.
 */
export default function LotSequenceForm({ action, method, initial, submitLabel, bare = false, onSuccess, onCancel }) {
    const { productTypes = [], patternTokens = [], csrf_token } = usePage().props;
    const form = useForm(initial);
    const { data, setData, errors, processing } = form;

    const [mode, setMode] = useState(initial.pattern ? 'pattern' : 'simple');
    const [preview, setPreview] = useState(null);
    const [previewError, setPreviewError] = useState(null);
    const patternInputRef = useRef(null);

    // Debounced server-side preview of the pattern being typed.
    useEffect(() => {
        if (mode !== 'pattern' || !data.pattern) {
            setPreview(null);
            setPreviewError(null);
            return;
        }
        const t = setTimeout(async () => {
            try {
                const r = await fetch('/admin/lot-sequences/preview', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf_token,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({
                        pattern: data.pattern,
                        pad_size: data.pad_size || 4,
                        product_type_id: data.product_type_id || null,
                    }),
                });
                if (r.status === 422) {
                    const body = await r.json();
                    setPreview(null);
                    setPreviewError(body.errors?.pattern?.[0] ?? 'Invalid pattern');
                    return;
                }
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                const body = await r.json();
                setPreview(body.preview);
                setPreviewError(null);
            } catch {
                setPreview(null);
                setPreviewError(null); // network hiccup — just hide the preview
            }
        }, 300);
        return () => clearTimeout(t);
    }, [mode, data.pattern, data.pad_size, data.product_type_id, csrf_token]);

    // A token dropped straight after another gets a dash between them: two
    // dates run together ("[year][month]") are legal but rarely meant, and the
    // dash is one keystroke to delete when they are.
    const insertToken = (token) => {
        const el = patternInputRef.current;
        const current = data.pattern ?? '';
        const focused = el && document.activeElement === el;
        const start = focused ? (el.selectionStart ?? current.length) : current.length;
        const end = focused ? (el.selectionEnd ?? current.length) : current.length;
        const tag = (current.slice(0, start).endsWith(']') ? '-' : '') + `[${token}]`;
        setData('pattern', current.slice(0, start) + tag + current.slice(end));
        if (focused) {
            requestAnimationFrame(() => {
                el.focus();
                el.setSelectionRange(start + tag.length, start + tag.length);
            });
        }
    };

    const switchMode = (m) => {
        setMode(m);
        if (m === 'simple') setData('pattern', '');
    };

    const submit = (e) => {
        e.preventDefault();
        form.submit(method, action, { preserveScroll: true, ...(onSuccess ? { onSuccess } : {}) });
    };

    return (
        <form onSubmit={submit} className={bare ? 'space-y-5' : 'bg-om-card rounded-om-sm shadow-sm p-6 max-w-2xl space-y-5'}>
            <TextField label={__('Name')} required value={data.name} error={errors.name} onChange={(v) => setData('name', v)} />

            <div>
                <div className="block text-sm font-medium text-om-muted mb-1">{__('Product Type')}</div>
                <Dropdown
                    aria-label={__('Product Type')}
                    value={data.product_type_id == null ? '' : String(data.product_type_id)}
                    onChange={(v) => setData('product_type_id', v)}
                    options={[
                        { value: '', label: `— ${__('None (default sequence)')} —` },
                        ...productTypes.map((p) => ({ value: String(p.id), label: p.name })),
                    ]}
                    className="w-full"
                />
                {errors.product_type_id && <p className="mt-1 text-xs text-om-blocked">{errors.product_type_id}</p>}
            </div>

            <div>
                <div className="block text-sm font-medium text-om-muted mb-1">{__('Numbers')}</div>
                <Dropdown
                    aria-label={__('Numbers')}
                    value={data.purpose ?? 'lot'}
                    onChange={(v) => setData('purpose', v)}
                    options={[
                        { value: 'lot', label: __('Batch LOT numbers') },
                        { value: 'process_serial', label: __('Process serial numbers (PSN)') },
                        { value: 'unit_serial', label: __('Unit serial numbers') },
                    ]}
                    className="w-full"
                />
                <p className="mt-1 text-xs text-om-muted">{__('LOT numbers go onto batches when they start or are released. Process and unit serials are issued at the SN Label Station for single units. A product may have one sequence of each kind.')}</p>
                {errors.purpose && <p className="mt-1 text-xs text-om-blocked">{errors.purpose}</p>}
            </div>

            {/* Mode toggle */}
            <div className="flex rounded-om-sm border border-om-line2 overflow-hidden w-fit text-sm">
                {[
                    ['pattern', __('Pattern')],
                    ['simple', __('Simple')],
                ].map(([m, label]) => (
                    <button
                        key={m}
                        type="button"
                        onClick={() => switchMode(m)}
                        className={`px-4 py-1.5 font-medium ${
                            mode === m ? 'bg-om-ink text-om-on-ink' : 'bg-om-card text-om-muted hover:bg-om-bg'
                        }`}
                    >
                        {label}
                    </button>
                ))}
            </div>

            {mode === 'pattern' ? (
                <div className="space-y-3">
                    <div>
                        <div className="block text-sm font-medium text-om-muted mb-1">
                            {__('Pattern')} <span className="text-om-blocked">*</span>
                        </div>
                        <input
                            aria-label={__('Pattern')}
                            ref={patternInputRef}
                            type="text"
                            value={data.pattern ?? ''}
                            onChange={(e) => setData('pattern', e.target.value)}
                            placeholder="test-[date]-[seq]-[hour]"
                            className="form-input w-full font-mono"
                        />
                        {(errors.pattern || previewError) && (
                            <p className="mt-1 text-xs text-om-blocked">{errors.pattern ?? previewError}</p>
                        )}
                    </div>

                    {/* Token palette: click to insert. Each chip shows its meaning
                        and today's value on hover, and the legend below spells them out. */}
                    <div className="flex flex-wrap gap-1.5">
                        {patternTokens.map((t) => (
                            <button
                                key={t.token}
                                type="button"
                                title={`${t.label} · ${t.example}`}
                                onMouseDown={(e) => e.preventDefault() /* keep input focus/cursor */}
                                onClick={() => insertToken(t.token)}
                                className="px-2 py-0.5 rounded-full bg-om-chip hover:bg-om-chip text-xs font-mono text-om-muted border border-om-line2"
                            >
                                [{t.token}]
                            </button>
                        ))}
                    </div>
                    <p className="text-xs text-om-muted">
                        {__('Click a token to insert it. Pattern must contain exactly one')} <code>[seq]</code>. {__('Anything outside the brackets is printed as typed.')}
                    </p>
                    <dl className="grid grid-cols-[auto_1fr_auto] gap-x-3 gap-y-0.5 rounded-om-sm border border-om-line2 bg-om-bg px-3 py-2 text-[11.5px]">
                        {patternTokens.map((t) => (
                            <Fragment key={t.token}>
                                <dt className="font-mono text-om-ink">[{t.token}]</dt>
                                <dd className="text-om-muted">{t.label}</dd>
                                <dd className="font-mono text-om-faint text-right">{t.example}</dd>
                            </Fragment>
                        ))}
                    </dl>

                    {preview && (
                        <div className="rounded-om-sm bg-om-panel border border-om-line2 px-3 py-2 text-sm">
                            <span className="text-om-muted">{__('Preview:')} </span>
                            <span className="font-mono font-medium text-om-ink">{preview}</span>
                        </div>
                    )}
                </div>
            ) : (
                <>
                    <TextField label={__('Prefix')} required value={data.prefix} error={errors.prefix} onChange={(v) => setData('prefix', v)} />
                    <TextField label={__('Suffix')} value={data.suffix} error={errors.suffix} onChange={(v) => setData('suffix', v)} />
                    <Checkbox
                        checked={!!data.year_prefix}
                        onChange={(next) => setData('year_prefix', next)}
                        label={__('Year Prefix')}
                    />
                </>
            )}

            <div className="grid grid-cols-2 gap-4">
                <div>
                    <div className="block text-sm font-medium text-om-muted mb-1">{__('Counter digits')}</div>
                    <input
                        aria-label={__('Counter digits')}
                        type="number"
                        min={1}
                        max={10}
                        value={data.pad_size ?? 4}
                        onChange={(e) => setData('pad_size', e.target.value)}
                        className="form-input w-full"
                    />
                    <p className="mt-1 text-xs text-om-muted">{__('How many digits [seq] is padded to: 4 gives 0001, 0002…; 1 gives 1, 2… with no leading zeros.')}</p>
                    {errors.pad_size && <p className="mt-1 text-xs text-om-blocked">{errors.pad_size}</p>}
                </div>
                <div>
                    <div className="block text-sm font-medium text-om-muted mb-1">{__('Counter starts over')}</div>
                    <Dropdown
                        aria-label={__('Counter starts over')}
                        value={data.reset_period == null ? 'none' : String(data.reset_period)}
                        onChange={(v) => setData('reset_period', v)}
                        options={RESET_PERIODS.map((o) => ({ value: String(o.value), label: __(o.label) }))}
                        className="w-full"
                    />
                    <p className="mt-1 text-xs text-om-muted">{__('When [seq] goes back to 1. Daily: the first number each day is 1 again, so a pattern with the date in it stays unique.')}</p>
                    {errors.reset_period && <p className="mt-1 text-xs text-om-blocked">{errors.reset_period}</p>}
                </div>
            </div>

            <div className="flex items-center gap-3 pt-2">
                <Button type="submit" variant="primary" loading={processing} disabled={processing}>
                    {submitLabel}
                </Button>
                {onCancel ? (
                    <button type="button" onClick={onCancel} className="text-om-muted hover:text-om-ink text-sm">{__('Cancel')}</button>
                ) : (
                    <Link href="/admin/lot-sequences" className="text-om-muted hover:text-om-ink text-sm">{__('Cancel')}</Link>
                )}
            </div>
        </form>
    );
}

function TextField({ label, required, value, error, onChange }) {
    return (
        <div>
            <div className="block text-sm font-medium text-om-muted mb-1">
                {label} {required && <span className="text-om-blocked">*</span>}
            </div>
            <input aria-label={label} type="text" value={value ?? ''} onChange={(e) => onChange(e.target.value)} className="form-input w-full" />
            {error && <p className="mt-1 text-xs text-om-blocked">{error}</p>}
        </div>
    );
}
