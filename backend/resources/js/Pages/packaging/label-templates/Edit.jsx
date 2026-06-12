import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import LabelTemplateForm from './Form';

export default function LabelTemplateEdit() {
    const { template, types = {}, sizes = {}, barcodeFormats = {}, availableFields = {} } = usePage().props;
    const form = useForm({
        name: template.name ?? '',
        type: template.type ?? 'work_order',
        size: template.size ?? '100x50',
        barcode_format: template.barcode_format ?? 'code128',
        fields: template.fields_config ?? {},
        is_default: !!template.is_default,
        is_active: !!template.is_active,
    });
    const submit = (e) => { e.preventDefault(); form.put(`/packaging/label-templates/${template.id}`); };

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${template.name}`} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">Edit Label Template</h1>
            <LabelTemplateForm form={form} types={types} sizes={sizes} barcodeFormats={barcodeFormats} availableFields={availableFields} submitLabel="Save Changes" onSubmit={submit} />
        </div>
    );
}

LabelTemplateEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
