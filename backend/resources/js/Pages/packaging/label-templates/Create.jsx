import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import LabelTemplateForm from './Form';

export default function LabelTemplateCreate() {
    const { types = {}, sizes = {}, barcodeFormats = {}, availableFields = {}, defaultFields = {} } = usePage().props;
    const form = useForm({
        name: '',
        type: 'work_order',
        size: '100x50',
        barcode_format: 'code128',
        fields: defaultFields,
        is_default: false,
        is_active: true,
    });
    const submit = (e) => { e.preventDefault(); form.post('/packaging/label-templates'); };

    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Label Template" />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">New Label Template</h1>
            <LabelTemplateForm form={form} types={types} sizes={sizes} barcodeFormats={barcodeFormats} availableFields={availableFields} submitLabel="Create" onSubmit={submit} />
        </div>
    );
}

LabelTemplateCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
