import { useEffect, useRef, useState } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { Button, Checkbox, Dropdown } from '@openmes/ui';

const RESET_PERIODS = [
    { value: 'none', label: 'No reset' },
    { value: 'yearly', label: 'Yearly' },
    { value: 'monthly', label: 'Monthly' },
    { value: 'daily', label: 'Daily' },
    { value: 'hourly', label: 'Hourly' },
];

/**
 * Create/edit form for LOT sequences with two modes:
 *  - Pattern: token template ("test-[date]-[seq]-[hour]") with a clickable
 *    token palette and a debounced live preview from the server.
 *  - Simple (legacy): prefix / year prefix / suffix.
 */
export default function LotSequenceForm({ action, method, initial, submitLabel }) {
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

    const insertToken = (token) => {
        const tag = `[${token}]`;
        const el = patternInputRef.current;
        const current = data.pattern ?? '';
        if (el && document.activeElement === el) {
            const start = el.selectionStart ?? current.length;
            const end = el.selectionEnd ?? current.length;
            setData('pattern', current.slice(0, start) + tag + current.slice(end));
            requestAnimationFrame(() => {
                el.focus();
                el.setSelectionRange(start + tag.length, start + tag.length);
            });
        } else {
            setData('pattern', current + tag);
        }
    };

    const switchMode = (m) => {
        setMode(m);
        if (m === 'simple') setData('pattern', '');
    };

    const submit = (e) => {
        e.preventDefault();
        form.submit(method, action);
    };

    return (
        <form onSubmit={submit} className="bg-om-card rounded-om-sm shadow-sm p-6 max-w-2xl space-y-5">
            <TextField label="Name" required value={data.name} error={errors.name} onChange={(v) => setData('name', v)} />

            <div>
                <label className="block text-sm font-medium text-om-muted mb-1">Product Type</label>
                <Dropdown
                    value={data.product_type_id == null ? '' : String(data.product_type_id)}
                    onChange={(v) => setData('product_type_id', v)}
                    options={[
                        { value: '', label: '— None (default sequence) —' },
                        ...productTypes.map((p) => ({ value: String(p.id), label: p.name })),
                    ]}
                    className="w-full"
                />
                {errors.product_type_id && <p className="mt-1 text-xs text-om-blocked">{errors.product_type_id}</p>}
            </div>

            {/* Mode toggle */}
            <div className="flex rounded-om-sm border border-om-line2 overflow-hidden w-fit text-sm">
                {[
                    ['pattern', 'Pattern'],
                    ['simple', 'Simple'],
                ].map(([m, label]) => (
                    <button
                        key={m}
                        type="button"
                        onClick={() => switchMode(m)}
                        className={`px-4 py-1.5 font-medium ${
                            mode === m ? 'bg-om-ink text-white' : 'bg-om-card text-om-muted hover:bg-om-bg'
                        }`}
                    >
                        {label}
                    </button>
                ))}
            </div>

            {mode === 'pattern' ? (
                <div className="space-y-3">
                    <div>
                        <label className="block text-sm font-medium text-om-muted mb-1">
                            Pattern <span className="text-om-blocked">*</span>
                        </label>
                        <input
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

                    {/* Token palette */}
                    <div className="flex flex-wrap gap-1.5">
                        {patternTokens.map((t) => (
                            <button
                                key={t}
                                type="button"
                                onMouseDown={(e) => e.preventDefault() /* keep input focus/cursor */}
                                onClick={() => insertToken(t)}
                                className="px-2 py-0.5 rounded-full bg-om-chip hover:bg-om-chip text-xs font-mono text-om-muted border border-om-line2"
                            >
                                [{t}]
                            </button>
                        ))}
                    </div>
                    <p className="text-xs text-om-muted">
                        Click a token to insert it. Pattern must contain exactly one <code>[seq]</code>. Use{' '}
                        <code>[date:y-m-d]</code> for a custom date format.
                    </p>

                    {preview && (
                        <div className="rounded-om-sm bg-om-panel border border-om-line2 px-3 py-2 text-sm">
                            <span className="text-om-muted">Preview: </span>
                            <span className="font-mono font-medium text-om-ink">{preview}</span>
                        </div>
                    )}
                </div>
            ) : (
                <>
                    <TextField label="Prefix" required value={data.prefix} error={errors.prefix} onChange={(v) => setData('prefix', v)} />
                    <TextField label="Suffix" value={data.suffix} error={errors.suffix} onChange={(v) => setData('suffix', v)} />
                    <Checkbox
                        checked={!!data.year_prefix}
                        onChange={(next) => setData('year_prefix', next)}
                        label="Year Prefix"
                    />
                </>
            )}

            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-om-muted mb-1">Pad Size</label>
                    <input
                        type="number"
                        min={1}
                        max={10}
                        value={data.pad_size ?? 4}
                        onChange={(e) => setData('pad_size', e.target.value)}
                        className="form-input w-full"
                    />
                    {errors.pad_size && <p className="mt-1 text-xs text-om-blocked">{errors.pad_size}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-om-muted mb-1">Counter Reset</label>
                    <Dropdown
                        value={data.reset_period == null ? 'none' : String(data.reset_period)}
                        onChange={(v) => setData('reset_period', v)}
                        options={RESET_PERIODS.map((o) => ({ value: String(o.value), label: o.label }))}
                        className="w-full"
                    />
                    {errors.reset_period && <p className="mt-1 text-xs text-om-blocked">{errors.reset_period}</p>}
                </div>
            </div>

            <div className="flex items-center gap-3 pt-2">
                <Button type="submit" variant="primary" loading={processing} disabled={processing}>
                    {submitLabel}
                </Button>
                <Link href="/admin/lot-sequences" className="text-om-muted hover:text-om-ink text-sm">
                    Cancel
                </Link>
            </div>
        </form>
    );
}

function TextField({ label, required, value, error, onChange }) {
    return (
        <div>
            <label className="block text-sm font-medium text-om-muted mb-1">
                {label} {required && <span className="text-om-blocked">*</span>}
            </label>
            <input type="text" value={value ?? ''} onChange={(e) => onChange(e.target.value)} className="form-input w-full" />
            {error && <p className="mt-1 text-xs text-om-blocked">{error}</p>}
        </div>
    );
}
