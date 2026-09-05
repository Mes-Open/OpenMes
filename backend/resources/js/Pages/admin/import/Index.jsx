import { useMemo, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Button, Dropdown, InlineAlert } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import PageTrail from '../../../components/PageTrail';
import FileDropZone from '../../../components/import/FileDropZone';
import OptionField from '../../../components/import/OptionField';
import AvailableFields from '../../../components/import/AvailableFields';
import SampleFiles from '../../../components/import/SampleFiles';
import ImportHistoryTable from '../../../components/import/ImportHistoryTable';
import useConfirm from '../../../components/useConfirm';
import { __ } from '../../../lib/i18n';
import { DELIMITER_LABELS, ENCODING_LABELS } from '../../../components/import/fileOptionLabels';
import AppDataTable from '../../../components/AppDataTable';
import { apiCall } from '../../../lib/http';

function optionDefaults(options) {
    const out = {};
    for (const o of options ?? []) {
        out[o.key] = o.default ?? (o.type === 'switch' ? false : '');
    }
    return out;
}

export default function ImportIndex() {
    const {
        basePath = '/admin/import',
        entities = [],
        entity,
        profiles = [],
        lines = [],
        limits = {},
        recentImports = [],
        userNames = {},
    } = usePage().props;

    const form = useForm({
        file: null,
        delimiter: 'auto',
        encoding: 'utf-8',
        mapping_id: '',
        options: optionDefaults(entity?.options),
    });

    const delimiters = limits.delimiters ?? ['auto', 'comma', 'semicolon', 'tab'];
    const encodings = limits.encodings ?? Object.keys(ENCODING_LABELS);
    const sizeHint = useMemo(
        () => __('Max :mb MB · .csv, .txt, .xlsx, .xls', { mb: limits.maxSizeMb ?? 32 }),
        [limits.maxSizeMb],
    );

    // The file is sent as soon as it is picked so the rows can be shown here,
    // rather than after committing to the mapping step. Everything the user
    // changes afterwards is persisted on submit, before navigating.
    const [preview, setPreview] = useState(null);
    const [busy, setBusy] = useState(false);

    const upload = async (file, fileOptions = {}) => {
        setPreview(null);

        if (!file) return;

        setBusy(true);
        form.clearErrors('file', 'delimiter', 'encoding');

        const body = new FormData();
        body.append('file', file);
        body.append('delimiter', fileOptions.delimiter ?? form.data.delimiter);
        body.append('encoding', fileOptions.encoding ?? form.data.encoding);

        const res = await fetch(`${basePath}/${entity.slug}/upload`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body,
        });

        setBusy(false);

        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            form.setError(data.errors ?? { file: data.message ?? __('The file could not be read. Check the format, separator and encoding.') });

            return;
        }

        setPreview(await res.json());
    };

    // Re-reads the file already on the server; no second upload.
    const reparse = async (fileOptions) => {
        if (!preview?.token) return;

        setBusy(true);
        const res = await apiCall(`${basePath}/${entity.slug}/preview/${preview.token}`, 'POST', {
            delimiter: fileOptions.delimiter ?? form.data.delimiter,
            encoding: fileOptions.encoding ?? form.data.encoding,
        });
        setBusy(false);

        if (!res.ok) return;

        const data = await res.json();
        setPreview((p) => ({ ...p, ...data }));
    };

    const setFileOption = (key, value) => {
        form.setData(key, value);
        if (preview?.token) reparse({ [key]: value });
    };

    // Memoised: AppDataTable rebuilds its column defs whenever this array's
    // identity changes, which an inline map() would do on every render.
    const previewColumns = useMemo(
        () => (preview?.headers ?? []).map((h) => ({
            id: h,
            header: h,
            accessorFn: (row) => row[h] ?? '',
            enableSorting: false,
        })),
        [preview?.headers],
    );

    const submit = async (e) => {
        e.preventDefault();

        // No token means the upload failed or is still running; fall back to the
        // plain post so the user still gets the usual validation errors.
        if (!preview?.token) {
            form.post(`${basePath}/${entity.slug}/upload`, { forceFormData: true });

            return;
        }

        setBusy(true);
        const res = await apiCall(`${basePath}/${entity.slug}/preview/${preview.token}`, 'POST', {
            delimiter: form.data.delimiter,
            encoding: form.data.encoding,
            options: form.data.options,
            mapping_id: form.data.mapping_id || null,
        });
        setBusy(false);

        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            form.setError(data.errors ?? {});

            return;
        }

        router.get(preview.mapUrl);
    };

    const optionError = (key) => form.errors[`options.${key}`];
    const selectedProfile = profiles.find((p) => String(p.id) === String(form.data.mapping_id ?? ''));
    const { confirm, dialog } = useConfirm();

    return (
        <div className="w-full">
            <Head title={__('Import')} />
            <PageTrail />

            {/* Same shell as the redesigned detail pages: a header bar, then
                bordered columns — no floating cards, no centred box. */}
            <div className="bg-om-card border-b border-om-line2">
                <div className="flex items-center gap-3 px-5 py-3 border-b border-om-line2 flex-wrap">
                    <h1 className="text-lg font-bold text-om-ink">{__('Import')}</h1>
                    <span className="text-[12px] text-om-muted">{__('Load product types, materials, work orders and recipes from a CSV or Excel file.')}</span>
                    {/* The header bar runs the full width and stood empty past the
                        subtitle; a sample file is what you reach for before picking
                        anything else on the page. The rule hides once the bar wraps,
                        where a dangling divider would read as a mistake. */}
                    <span className="hidden sm:block self-center h-4 w-px bg-om-line2" aria-hidden />
                    <SampleFiles basePath={basePath} entities={entities} inline />
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3">
                    <form onSubmit={submit} className="lg:col-span-2 lg:border-r border-om-line2 px-5 py-4 space-y-5">
                        {/* One short select does not need the full column, so the
                            label sits beside it — but the description stays under
                            the pair, the way every other field on this page reads. */}
                        <div>
                            <div className="flex flex-wrap items-center gap-x-4 gap-y-1">
                                <div className="form-label shrink-0 !mb-0">{__('What do you want to import?')} <span className="text-om-blocked">*</span></div>
                                <Dropdown
                                    aria-label={__('What do you want to import?')}
                                    className="w-full sm:w-64"
                                    value={entity?.slug ?? ''}
                                    onChange={(slug) => { if (slug !== entity?.slug) router.get(`${basePath}/${slug}`); }}
                                    options={entities.map((e) => ({ value: e.slug, label: e.label }))}
                                />
                            </div>
                            {entity?.description && <p className="text-xs text-om-muted mt-1">{entity.description}</p>}
                        </div>

                        {entity?.warnings?.length > 0 && (
                            <InlineAlert severity="warning" title={__('Before you import')}>
                                <ul className="list-disc pl-4 space-y-0.5">
                                    {entity.warnings.map((w, i) => <li key={i}>{w}</li>)}
                                </ul>
                            </InlineAlert>
                        )}

                        <div>
                            <div className="form-label">{__('Select a file to import')} <span className="text-om-blocked">*</span></div>
                            <FileDropZone
                                file={form.data.file}
                                onChange={(f) => { form.setData('file', f); upload(f); }}
                                hint={sizeHint}
                                error={form.errors.file}
                            />
                            <p className="text-xs text-om-faint mt-1">{__('Only UTF-8, ISO-8859-1 and Windows-1250 encodings are supported.')}</p>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4">
                            <div>
                                <div className="form-label">{__('Field separator')}</div>
                                <Dropdown
                                    aria-label={__('Field separator')}
                                    className="w-full"
                                    value={form.data.delimiter}
                                    onChange={(v) => setFileOption('delimiter', v)}
                                    options={delimiters.map((d) => ({ value: d, label: DELIMITER_LABELS[d]?.() ?? d }))}
                                />
                                <p className="text-xs text-om-faint mt-1">{__('e.g. 1; Hoodie; 129.90')}</p>
                                {form.errors.delimiter && <p className="text-xs text-om-blocked mt-1">{form.errors.delimiter}</p>}
                            </div>
                            <div>
                                <div className="form-label">{__('File encoding')}</div>
                                <Dropdown
                                    aria-label={__('File encoding')}
                                    className="w-full"
                                    value={form.data.encoding}
                                    onChange={(v) => setFileOption('encoding', v)}
                                    options={encodings.map((e) => ({ value: e, label: ENCODING_LABELS[e] ?? e }))}
                                />
                                <p className="text-xs text-om-faint mt-1">{__('Excel on Windows usually saves Polish text as Windows-1250.')}</p>
                                {form.errors.encoding && <p className="text-xs text-om-blocked mt-1">{form.errors.encoding}</p>}
                            </div>

                            {profiles.length > 0 && (
                                <div className="md:col-span-2">
                                    <div className="form-label">{__('Load Mapping Profile (optional)')}</div>
                                    <div className="flex items-start gap-2">
                                        <Dropdown
                                            aria-label={__('Load Mapping Profile (optional)')}
                                            className="flex-1 min-w-0"
                                            value={form.data.mapping_id == null ? '' : String(form.data.mapping_id)}
                                            onChange={(v) => form.setData('mapping_id', v)}
                                            options={[
                                                { value: '', label: __('— Map columns manually —') },
                                                ...profiles.map((p) => ({ value: String(p.id), label: p.is_default ? `${p.name} (${__('default')})` : p.name })),
                                            ]}
                                        />
                                        {selectedProfile?.own && (
                                            <Button
                                                variant="ghost"
                                                onClick={() => confirm(
                                                    { title: __('Delete mapping profile?'), description: selectedProfile.name },
                                                    () => router.delete(`${basePath}/profiles/${selectedProfile.id}`, {
                                                        preserveScroll: true,
                                                        onSuccess: () => form.setData('mapping_id', ''),
                                                    }),
                                                )}
                                            >
                                                {__('Delete this profile')}
                                            </Button>
                                        )}
                                    </div>
                                    {form.errors.mapping_id && <p className="text-xs text-om-blocked mt-1">{form.errors.mapping_id}</p>}
                                </div>
                            )}

                            {entity?.options?.map((o) => (
                                <OptionField
                                    key={o.key}
                                    option={o}
                                    lines={lines}
                                    value={form.data.options?.[o.key]}
                                    onChange={(v) => form.setData('options', { ...form.data.options, [o.key]: v })}
                                    error={optionError(o.key)}
                                />
                            ))}
                        </div>

                        {/* What the file actually contains, read the way the current
                            separator and encoding read it — before committing to
                            the mapping step. */}
                        {preview && (
                            <div className="border border-om-line2 rounded-om-sm">
                                <div className="flex items-baseline gap-2 px-3 py-2 border-b border-om-line2 flex-wrap">
                                    <h2 className="text-sm font-bold text-om-ink">{__('Data Preview')}</h2>
                                    <span className="text-xs text-om-muted">
                                        {__(':shown of :total rows', { shown: preview.previewRows.length, total: preview.totalRows })}
                                        {' · '}
                                        {__(':n columns', { n: preview.headers.length })}
                                    </span>
                                </div>

                                {preview.warnings?.length > 0 && (
                                    <div className="px-3 py-2 space-y-2 border-b border-om-line2">
                                        {preview.warnings.map((w) => (
                                            <InlineAlert key={w.code} severity="warning" title={w.message}>
                                                {w.fix && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        disabled={busy}
                                                        onClick={() => {
                                                            if (w.fix.delimiter) setFileOption('delimiter', w.fix.delimiter);
                                                            if (w.fix.encoding) setFileOption('encoding', w.fix.encoding);
                                                        }}
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

                                <AppDataTable
                                    data={preview.previewRows}
                                    columns={previewColumns}
                                    getRowId={(_, index) => String(index)}
                                    columnToggle={false}
                                    emptyLabel={__('No rows to preview.')}
                                />
                            </div>
                        )}

                        <Button type="submit" variant="primary" loading={form.processing || busy} disabled={!form.data.file}>
                            {__('Upload & map columns')}
                        </Button>
                    </form>

                    <div className="divide-y divide-om-line2 border-t lg:border-t-0 border-om-line2">
                        <AvailableFields entity={entity} />
                        <ImportHistoryTable basePath={basePath} recentImports={recentImports} entities={entities} userNames={userNames} />
                    </div>
                </div>
            </div>
            {dialog}
        </div>
    );
}

ImportIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
