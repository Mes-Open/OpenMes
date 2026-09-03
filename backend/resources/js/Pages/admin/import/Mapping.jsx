import { useMemo, useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Button, Checkbox, Dropdown, InlineAlert, TextField } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import PageTrail from '../../../components/PageTrail';
import AppDataTable from '../../../components/AppDataTable';
import { autoDetect } from '../../../components/import/useAutoDetect';
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

    const [rows, setRows] = useState(() => buildRows(headers, initialMapping ?? autoDetect(headers, fields)));
    const [saveProfile, setSaveProfile] = useState(false);
    const [profileId, setProfileId] = useState('');

    const form = useForm({
        token,
        mapping: {},
        save_mapping_name: '',
        dry_run: false,
    });

    const setRow = (h, patch) => setRows((prev) => ({ ...prev, [h]: { ...prev[h], ...patch } }));

    const resolved = useMemo(() => {
        const out = {};
        for (const h of headers) {
            const r = rows[h] ?? { select: IGNORE, customKey: '' };
            if (r.select === CUSTOM) {
                const key = (r.customKey || '').trim();
                out[h] = key ? `custom:${key}` : IGNORE;
            } else {
                out[h] = r.select || IGNORE;
            }
        }
        return out;
    }, [rows, headers]);

    // Header names are free text (dots, spaces), so address cells by function, not key.
    const previewColumns = useMemo(
        () => headers.map((h) => ({ id: h, header: h, accessorFn: (row) => row[h] ?? '', enableSorting: false })),
        [headers],
    );

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
                        <span className="font-medium text-om-ink">{__(':count rows', { count: totalRows })}</span>
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
                            {__('Run Import (:count rows)', { count: totalRows })}
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
                                    <Button variant="ghost" size="sm" onClick={() => setRows(buildRows(headers, autoDetect(headers, fields)))}>
                                        {__('Auto-detect')}
                                    </Button>
                                    <Button variant="ghost" size="sm" onClick={() => setRows(buildRows(headers, null))}>
                                        {__('Clear all')}
                                    </Button>
                                </div>
                            </div>

                            <div className="divide-y divide-om-line2 border-y border-om-line2">
                                {headers.map((h) => {
                                    const r = rows[h] ?? { select: IGNORE, customKey: '' };
                                    const field = fieldKeys.has(r.select) ? fields.find((f) => f.key === r.select) : null;
                                    const sample = previewRows[0]?.[h] ?? '—';
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
                            <h2 className="px-5 py-3 text-sm font-bold text-om-ink">
                                {__('Data Preview')}{' '}
                                <span className="text-xs font-normal text-om-muted">{__('(first :n rows)', { n: previewRows.length })}</span>
                            </h2>
                            <AppDataTable
                                data={previewRows}
                                columns={previewColumns}
                                getRowId={(_, index) => String(index)}
                                searchable={false}
                                columnToggle={false}
                                paginated={false}
                                bodyMaxHeight={null}
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
                                        if (p) setRows(buildRows(headers, p.column_mappings ?? {}));
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
                                <div className="flex justify-between py-1.5"><dt className="text-om-muted">{__('Total rows:')}</dt><dd className="font-medium">{totalRows}</dd></div>
                                <div className="flex justify-between py-1.5"><dt className="text-om-muted">{__('Columns:')}</dt><dd className="font-medium">{headers.length}</dd></div>
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
