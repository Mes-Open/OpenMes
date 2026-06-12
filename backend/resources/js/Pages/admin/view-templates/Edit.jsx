import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ViewTemplateForm from './Form';

export default function ViewTemplateEdit({ viewTemplate }) {
    const form = useForm({
        name: viewTemplate.name ?? '',
        description: viewTemplate.description ?? '',
        columns: viewTemplate.columns ?? [],
    });
    const submit = (e) => { e.preventDefault(); form.put(`/admin/view-templates/${viewTemplate.id}`); };

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${viewTemplate.name}`} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">Edit View Template</h1>
            <ViewTemplateForm form={form} submitLabel="Save Changes" onSubmit={submit} />
        </div>
    );
}

ViewTemplateEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
