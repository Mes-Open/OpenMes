import { useMemo } from 'react';
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

const DELIMITER_LABELS = {
    auto: () => __('Auto-detect'),
    comma: () => __('Comma (,)'),
    semicolon: () => __('Semicolon (;)'),
    tab: () => __('Tab'),
};

const ENCODING_LABELS = {
    'utf-8': 'UTF-8',
    'iso-8859-1': 'ISO-8859-1',
    'windows-1250': 'Windows-1250',
};

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

    const submit = (e) => {
        e.preventDefault();
        form.post(`${basePath}/${entity.slug}/upload`, { forceFormData: true });
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
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3">
                    <form onSubmit={submit} className="lg:col-span-2 lg:border-r border-om-line2 px-5 py-4 space-y-5">
                        <div>
                            <div className="form-label">{__('What do you want to import?')} <span className="text-om-blocked">*</span></div>
                            <Dropdown
                                aria-label={__('What do you want to import?')}
                                className="w-full"
                                value={entity?.slug ?? ''}
                                onChange={(slug) => { if (slug !== entity?.slug) router.get(`${basePath}/${slug}`); }}
                                options={entities.map((e) => ({ value: e.slug, label: e.label }))}
                            />
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
                                onChange={(f) => form.setData('file', f)}
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
                                    onChange={(v) => form.setData('delimiter', v)}
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
                                    onChange={(v) => form.setData('encoding', v)}
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

                        <Button type="submit" variant="primary" loading={form.processing} disabled={!form.data.file}>
                            {__('Upload & map columns')}
                        </Button>
                    </form>

                    <div className="divide-y divide-om-line2 border-t lg:border-t-0 border-om-line2">
                        <AvailableFields entity={entity} />
                        <SampleFiles basePath={basePath} entities={entities} />
                        <ImportHistoryTable basePath={basePath} recentImports={recentImports} entities={entities} userNames={userNames} />
                    </div>
                </div>
            </div>
            {dialog}
        </div>
    );
}

ImportIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
