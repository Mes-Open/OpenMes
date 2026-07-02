import { Head, useForm, usePage } from '@inertiajs/react';
import { __ } from '../../../lib/i18n';
import { Button, Checkbox } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';

export default function ProcessTemplatesEdit() {
    const { productType, processTemplate } = usePage().props;

    const form = useForm({
        name: processTemplate.name ?? '',
        is_active: !!processTemplate.is_active,
    });

    const { data, setData, errors, processing } = form;

    const submit = (e) => {
        e.preventDefault();
        form.put(
            `/admin/product-types/${productType.id}/process-templates/${processTemplate.id}`,
        );
    };

    return (
        <>
            <Head title={__("Edit Process Template")} />

            <div className="max-w-2xl mx-auto">
                <div className="mb-6">
                    <a
                        href={`/admin/product-types/${productType.id}/process-templates`}
                        className="text-om-accent hover:text-om-accent flex items-center gap-2 mb-4"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        {__("Back to Templates")}
                    </a>
                    <h1 className="text-3xl font-bold text-om-ink">{__("Edit Process Template")}</h1>
                    <p className="text-sm text-om-muted mt-1">
                        {productType.name} — {__("Version")} {processTemplate.version}
                    </p>
                </div>

                <div className="card">
                    <form onSubmit={submit}>
                        <div className="mb-6">
                            <label htmlFor="name" className="form-label">{__("Template Name")}</label>
                            <input
                                type="text"
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className={`form-input w-full${errors.name ? ' border-om-blocked' : ''}`}
                                placeholder={__("e.g., Standard Assembly Process, Quality Inspection v2")}
                                required
                                autoFocus
                            />
                            <p className="text-sm text-om-muted mt-1">{__("Descriptive name for this manufacturing process")}</p>
                            {errors.name && <p className="text-om-blocked text-sm mt-1">{errors.name}</p>}
                        </div>

                        <div className="mb-6">
                            <Checkbox
                                checked={data.is_active}
                                onChange={(next) => setData('is_active', next)}
                                label={__("Active (template is ready for use in work orders)")}
                            />
                        </div>

                        <div className="flex justify-end gap-3">
                            <a
                                href={`/admin/product-types/${productType.id}/process-templates`}
                                className="btn-touch btn-secondary"
                            >
                                {__("Cancel")}
                            </a>
                            <Button type="submit" variant="primary" loading={processing} disabled={processing}>
                                {processing ? __('Saving…') : __('Update Template')}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}

ProcessTemplatesEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
