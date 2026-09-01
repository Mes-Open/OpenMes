import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { triggerFields, triggerInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function QualityControlTriggerEdit() {
    const { trigger, templates, lines, workstations, productTypes } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('Edit Quality Control Trigger')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('Edit Quality Control Trigger')}</h1>
            <ResourceForm
                action={`/admin/quality-control-triggers/${trigger.id}`}
                method="put"
                fields={triggerFields({ templates, lines, workstations, productTypes })}
                initial={triggerInitial(trigger)}
                submitLabel={__('Save')}
                cancelHref="/admin/quality-control-triggers"
            />
        </div>
    );
}

QualityControlTriggerEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
