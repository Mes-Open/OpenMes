import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Button, Checkbox, Dropdown, InlineAlert, TextField } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import PageTrail from '../../../components/PageTrail';
import AppDataTable from '../../../components/AppDataTable';
import { autoDetect } from '../../../components/import/useAutoDetect';
import { delimiterOptions, encodingOptions } from '../../../components/import/fileOptionLabels';
import { apiCall } from '../../../lib/http';
import { __ } from '../../../lib/i18n';

const IGNORE = '_ignore';
const CUSTOM = '__custom__';

// Per-header row state: { select, customKey }
function buildRows(headers, mapping) {
    const out = {};
    for (const h of headers) {
        const raw = String(mapping?.[h] ?? IGNORE);
        const isCustom = raw.startsWith('custom:');
        out[h] = { select: isCustom ? CUSTOM : raw, customKey: isCustom ? raw.slice(7) : '' };
    }
    return out;
}

export default function ImportMapping() {
    const {
        basePath = '/admin/import',
        entity,
        profiles = [],
        headers = [],
        previewRows = [],
        totalRows = 0,
        warnings: initialWarnings = [],
        fileOptions: initialFileOptions = {},
        limits = {},
        token = '',
        originalFilename = '',
        initialMapping = null,
        errors: pageErrors = {},
    } = usePage().props;

    // The identifier check and any rejected mapping value both come back as
    // validation errors on this page (import.map), not as a re-render.
    const mappingError = pageErrors.mapping
        ?? Object.entries(pageErrors).find(([k]) => k.startsWith('mapping.'))?.[1]
        ?? pageErrors.token
        ?? null;

    const fields = entity?.fields ?? [];
    const fieldKeys = useMemo(() => new Set(fields.map((f) => f.key)), [fields]);

    // Separator and encoding are picked on the upload screen before any row is
    // visible. Re-reading the stored file here makes a wrong guess correctable
    // without re-uploading, so the parsed shape is state, not a fixed prop.
    const [parse, setParse] = useState({
        headers,
        previewRows,
        totalRows,
        warnings: initialWarnings,
        problems: {},
        delimiter: initialFileOptions.delimiter ?? 'auto',
        encoding: initialFileOptions.encoding ?? 'utf-8',
        loading: false,
    });

    const [rows, setRows] = useState(() => buildRows(
        headers,
        // An unresolvable profile (one written for another entity, say) comes
        // back as `[]`, which is not nullish — so `??` kept it and every column
        // rendered as "ignore" with auto-detection silently skipped.
        initialMapping && Object.keys(initialMapping).length > 0
            ? initialMapping
            : autoDetect(headers, fields),
    ));
    const [saveProfile, setSaveProfile] = useState(false);
    const [profileId, setProfileId] = useState('');

    const form = useForm({
        token,
        mapping: {},
        save_mapping_name: '',
        dry_run: false,
    });

    // Re-reads the stored upload with the given settings. A changed separator
    // yields different headers, so the mapping is rebuilt by auto-detection
    // rather than kept — a mapping keyed by columns that no longer exist would
    // silently ignore every one of them.
    const seq = useRef(0);
    // The mapping the cell check should run against. Held in a ref because a
    // re-parse triggered by a warning's fix button carries no mapping of its
    // own, and sending none would silently clear every marked cell until the
    // user next touched a column.
    const mappingRef = useRef({});
    const reparse = useCallback(async (next) => {
        const mine = ++seq.current;
        setParse((p) => ({ ...p, ...next, loading: true }));

        const res = await apiCall(`${basePath}/${entity.slug}/preview/${token}`, 'POST', {
            delimiter: next.delimiter ?? parse.delimiter,
            encoding: next.encoding ?? parse.encoding,
            mapping: next.mapping ?? mappingRef.current,
        });

        // A slower earlier request must not overwrite a newer answer.
        if (mine !== seq.current) return;

        if (!res.ok) {
            setParse((p) => ({ ...p, loading: false }));
            return;
        }

        const data = await res.json();

        setParse((p) => ({
            ...p,
            headers: data.headers,
            previewRows: data.previewRows,
            totalRows: data.totalRows,
            warnings: data.warnings ?? [],
            problems: data.problems ?? {},
            delimiter: data.fileOptions.delimiter,
            encoding: data.fileOptions.encoding,
            loading: false,
        }));

        if (next.mapping === undefined) {
            setRows(buildRows(data.headers, autoDetect(data.headers, fields)));
        }
    }, [basePath, entity?.slug, token, parse.delimiter, parse.encoding, fields]);

    const setRow = (h, patch) => setRows((prev) => ({ ...prev, [h]: { ...prev[h], ...patch } }));

    const resolved = useMemo(() => {
        const out = {};
        for (const h of parse.headers) {
            const r = rows[h] ?? { select: IGNORE, customKey: '' };
            if (r.select === CUSTOM) {
                const key = (r.customKey || '').trim();
                out[h] = key ? `custom:${key}` : IGNORE;
            } else {
                out[h] = r.select || IGNORE;
            }
        }
        return out;
    }, [rows, parse.headers]);

    // Header names are free text (dots, spaces), so address cells by function, not key.
    const previewColumns = useMemo(
        () => parse.headers.map((h) => ({
            id: h,
            header: h,
            accessorFn: (row) => row[h] ?? '',
            enableSorting: false,
            // A value its mapped field would reject is marked on the cell that
            // causes it, rather than as a row number in a list after the run.
            cell: ({ getValue, row }) => {
                const reason = parse.problems?.[row.index]?.[h];
                const value = getValue();

                return reason
                    ? (
                        <span className="inline-flex items-center gap-1 rounded-om-sm bg-om-blocked-bg px-1.5 py-0.5 text-om-blocked" title={reason}>
                            {value === '' ? '—' : value}
                        </span>
                    )
                    : value;
            },
        })),
        [parse.headers, parse.problems],
    );

    const problemCount = useMemo(
        () => Object.values(parse.problems ?? {}).reduce((n, r) => n + Object.keys(r).length, 0),
        [parse.problems],
    );

    // Which cells are rejected depends on what each column is mapped to, so the
    // check re-runs as the mapping changes — debounced, since a custom-field key
    // is typed a character at a time.
    const mappingKey = JSON.stringify(resolved);
    mappingRef.current = resolved;
    useEffect(() => {
        const id = setTimeout(() => reparse({ mapping: JSON.parse(mappingKey) }), 400);

        return () => clearTimeout(id);
        // reparse is intentionally out: it changes with every parse state update,
        // which would restart this timer forever.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [mappingKey]);

    const mappedCount = Object.values(resolved).filter((t) => t !== IGNORE).length;
    const requiredMissing = fields
        .filter((f) => f.required && !Object.values(resolved).includes(f.key))
        .map((f) => f.label);

    // Both buttons post the same form; only `dry_run` differs. transform() runs
    // on submit, so the flag is read from the click rather than from form state
    // (setData is async — the first click would post the previous value).
    const run = (dryRun) => {
        form.transform((data) => ({
            ...data,
            mapping: resolved,
            save_mapping_name: saveProfile ? data.save_mapping_name : '',
            dry_run: dryRun,
        }));
        form.post(`${basePath}/${entity.slug}/process`);
    };

    const submit = (e) => {
        e.preventDefault();
        run(false);
    };

    const fieldOptions = [
        { value: IGNORE, label: __('— Ignore this column —') },
        ...fields.map((f) => ({ value: f.key, label: f.required ? `${f.label} *` : f.label })),
        ...(entity?.allowsCustomFields ? [{ value: CUSTOM, label: __('Custom field…') }] : []),
    ];

    return (
        <div className="w-full">
            <Head title={__('Map Columns')} />
            <PageTrail append={[{ label: __('Map Columns') }]} />

            <form onSubmit={submit} className="bg-om-card border-b border-om-line2">
                <div className="flex items-center gap-3 px-5 py-3 border-b border-om-line2 flex-wrap">
                    <h1 className="text-lg font-bold text-om-ink">{__('Map Columns')}</h1>
                    <span className="text-[12px] text-om-muted">
                        {entity?.label}
                        {' · '}
                        <span className="font-medium text-om-ink">{__(':count rows', { count: parse.totalRows })}</span>
                        {originalFilename && <> {' · '}<span className="font-mono">{originalFilename}</span></>}
                    </span>
                    <div className="ml-auto flex items-center gap-2">
                        <Link href={`${basePath}/${entity?.slug ?? ''}`} className="inline-flex items-center justify-center rounded-om-sm border border-om-line px-4 py-[9px] text-[13px] font-semibold text-om-ink hover:bg-om-chip transition-colors">
                            {__('Back')}
                        </Link>
                        <Button type="button" variant="secondary" loading={form.processing} onClick={() => run(true)}>
                            {__('Validate only')}
                        </Button>
                        <Button type="submit" variant="primary" loading={form.processing}>
                            {__('Run Import (:count rows)', { count: parse.totalRows })}
                        </Button>
                    </div>
                </div>

                {mappingError && (
                    <div className="px-5 py-3 border-b border-om-line2">
                        <InlineAlert severity="error" title={mappingError} />
                    </div>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-3">
                    <div className="lg:col-span-2 lg:border-r border-om-line2">
                        <div className="px-5 py-4">
                            <div className="flex items-center justify-between mb-2">
                                <h2 className="text-sm font-bold text-om-ink">{__('Column Mapping')}</h2>
                                <div className="flex items-center gap-2">
                                    <span className="text-xs text-om-muted">{__('Quick-fill:')}</span>
                                    <Button variant="ghost" size="sm" onClick={() => setRows(buildRows(parse.headers, autoDetect(parse.headers, fields)))}>
                                        {__('Auto-detect')}
                                    </Button>
                                    <Button variant="ghost" size="sm" onClick={() => setRows(buildRows(parse.headers, null))}>
                                        {__('Clear all')}
                                    </Button>
                                </div>
                            </div>

                            <div className="divide-y divide-om-line2 border-y border-om-line2">
                                {parse.headers.map((h) => {
                                    const r = rows[h] ?? { select: IGNORE, customKey: '' };
                                    const field = fieldKeys.has(r.select) ? fields.find((f) => f.key === r.select) : null;
                                    const sample = parse.previewRows[0]?.[h] ?? '—';
                                    return (
                                        <div key={h} className="flex items-start gap-4 py-3">
                                            <div className="flex-shrink-0 w-44">
                                                <p className="text-sm font-mono font-medium text-om-ink truncate" title={h}>{h}</p>
                                                <p className="text-[11px] text-om-faint">{__('File column')}</p>
                                            </div>
                                            <div className="flex-shrink-0 pt-2 text-om-faint">&rarr;</div>
                                            <div className="flex-1 min-w-0">
                                                <Dropdown
                                                    aria-label={__('Target field for :column', { column: h })}
                                                    className="w-full text-sm"
                                                    value={r.select}
                                                    onChange={(v) => setRow(h, { select: v })}
                                                    options={fieldOptions}
                                                />
                                                {r.select === CUSTOM && (
                                                    <div className="mt-2">
                                                        <TextField
                                                            aria-label={__('Custom field key')}
                                                            placeholder={__('e.g. batch_code, color, weight_kg')}
                                                            value={r.customKey}
                                                            onChange={(v) => setRow(h, { customKey: v })}
                                                            hint={__('Stored as custom:your_key')}
                                                            maxLength={50}
                                                        />
                                                    </div>
                                                )}
                                                {field?.required && (
                                                    <span className="block mt-1 text-xs text-om-blocked font-medium">{__('required field')}</span>
                                                )}
                                                {field?.description && !field.required && (
                                                    <span className="block mt-1 text-xs text-om-faint">{field.description}</span>
                                                )}
                                            </div>
                                            <div className="flex-shrink-0 w-36 hidden md:block">
                                                <p className="text-[11px] text-om-faint">{__('Sample')}</p>
                                                <p className="text-xs text-om-muted font-mono truncate" title={sample}>{sample}</p>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        <div className="border-t border-om-line2">
                            <div className="px-5 py-3 flex items-end gap-4 flex-wrap">
                                <h2 className="text-sm font-bold text-om-ink mr-auto">
                                    {__('Data Preview')}{' '}
                                    <span className="text-xs font-normal text-om-muted">
                                        {__(':shown of :total rows', { shown: parse.previewRows.length, total: parse.totalRows })}
                                        {' · '}
                                        {__(':n columns', { n: parse.headers.length })}
                                    </span>
                                </h2>

                                {/* The same two settings the upload step asked for, where
                                    the rows they produce are finally visible. */}
                                <div className="w-44">
                                    <Dropdown
                                        label={__('Field separator')}
                                        value={parse.delimiter}
                                        onChange={(v) => reparse({ delimiter: v })}
                                        options={delimiterOptions(limits.delimiters)}
                                        disabled={parse.loading}
                                    />
                                </div>
                                <div className="w-44">
                                    <Dropdown
                                        label={__('File encoding')}
                                        value={parse.encoding}
                                        onChange={(v) => reparse({ encoding: v })}
                                        options={encodingOptions(limits.encodings)}
                                        disabled={parse.loading}
                                    />
                                </div>
                            </div>

                            {parse.warnings.length > 0 && (
                                <div className="px-5 pb-3 space-y-2">
                                    {parse.warnings.map((w) => (
                                        <InlineAlert key={w.code} severity="warning" title={w.message}>
                                            {w.fix && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    disabled={parse.loading}
                                                    onClick={() => reparse(w.fix)}
                                                >
                                                    {w.fix.encoding
                                                        ? __('Read as :encoding', { encoding: w.fix.encoding })
                                                        : __('Use this separator')}
                                                </Button>
                                            )}
                                        </InlineAlert>
                                    ))}
                                </div>
                            )}

                            {problemCount > 0 && (
                                <div className="px-5 pb-3">
                                    <InlineAlert
                                        severity="warning"
                                        title={__('Rejected cells in the preview: :n. Hover a marked cell for the reason.', { n: problemCount })}
                                    />
                                </div>
                            )}
                            <AppDataTable
                                data={parse.previewRows}
                                columns={previewColumns}
                                getRowId={(_, index) => String(index)}
                                columnToggle={false}
                                emptyLabel={__('No rows to preview.')}
                            />
                        </div>
                    </div>

                    <div className="divide-y divide-om-line2 border-t lg:border-t-0 border-om-line2">
                        {profiles.length > 0 && (
                            <div className="px-5 py-4">
                                <h3 className="text-sm font-bold text-om-ink mb-2">{__('Load Saved Profile')}</h3>
                                <Dropdown
                                    aria-label={__('Load Saved Profile')}
                                    className="w-full"
                                    value={profileId}
                                    onChange={(id) => {
                                        setProfileId(id);
                                        const p = profiles.find((x) => String(x.id) === String(id));
                                        if (p) setRows(buildRows(parse.headers, p.column_mappings ?? {}));
                                    }}
                                    options={[
                                        { value: '', label: __('— Map columns manually —') },
                                        ...profiles.map((p) => ({
                                            value: String(p.id),
                                            label: `${p.name}${p.is_default ? ` (${__('default')})` : ''} · ${__(':n columns mapped', { n: Object.keys(p.column_mappings ?? {}).length })}`,
                                        })),
                                    ]}
                                />
                            </div>
                        )}

                        <div className="px-5 py-4">
                            <h3 className="text-sm font-bold text-om-ink mb-2">{__('Save Mapping Profile')}</h3>
                            <Checkbox
                                className="mb-2"
                                checked={saveProfile}
                                onChange={(next) => setSaveProfile(next)}
                                label={__('Save this mapping for later')}
                            />
                            {saveProfile && (
                                <TextField
                                    aria-label={__('Profile name')}
                                    placeholder={__('Profile name (e.g. ERP Export)')}
                                    value={form.data.save_mapping_name}
                                    onChange={(v) => form.setData('save_mapping_name', v)}
                                    error={form.errors.save_mapping_name}
                                    maxLength={100}
                                />
                            )}
                        </div>

                        <div className="px-5 py-4">
                            <h3 className="text-sm font-bold text-om-ink mb-2">{__('Import Summary')}</h3>
                            <dl className="text-sm divide-y divide-om-line2">
                                <div className="flex justify-between py-1.5"><dt className="text-om-muted">{__('Entity')}</dt><dd className="font-medium">{entity?.label}</dd></div>
                                <div className="flex justify-between py-1.5"><dt className="text-om-muted">{__('Total rows:')}</dt><dd className="font-medium">{parse.totalRows}</dd></div>
                                <div className="flex justify-between py-1.5"><dt className="text-om-muted">{__('Columns:')}</dt><dd className="font-medium">{parse.headers.length}</dd></div>
                                <div className="flex justify-between py-1.5"><dt className="text-om-muted">{__('Mapped:')}</dt><dd className="font-medium">{mappedCount}</dd></div>
                            </dl>
                            {requiredMissing.length > 0 && (
                                <p className="mt-2 text-xs text-om-blocked">{__('Not yet mapped: :fields', { fields: requiredMissing.join(', ') })}</p>
                            )}
                        </div>
                    </div>
                </div>
            </form>
        </div>
    );
}

ImportMapping.layout = (page) => <AppLayout>{page}</AppLayout>;
