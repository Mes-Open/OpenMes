import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ViewTemplateForm from './Form';
import { __ } from '../../../lib/i18n';

export default function ViewTemplateEdit({ viewTemplate }) {
    const form = useForm({
        name: viewTemplate.name ?? '',
        description: viewTemplate.description ?? '',
        columns: viewTemplate.columns ?? [],
    });
    const submit = (e) => { e.preventDefault(); form.put(`/admin/view-templates/${viewTemplate.id}`); };

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('Edit :name', { name: viewTemplate.name })} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('Edit View Template')}</h1>
            <ViewTemplateForm form={form} submitLabel={__('Save Changes')} onSubmit={submit} />
        </div>
    );
}

ViewTemplateEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
