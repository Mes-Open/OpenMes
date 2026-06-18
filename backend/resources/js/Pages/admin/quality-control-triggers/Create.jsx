import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { triggerFields, TRIGGER_INITIAL } from './fields';
import { __ } from '../../../lib/i18n';

export default function QualityControlTriggerCreate() {
    const { templates, lines, workstations, productTypes } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Quality Control Trigger')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('New Quality Control Trigger')}</h1>
            <ResourceForm
                action="/admin/quality-control-triggers"
                method="post"
                fields={triggerFields({ templates, lines, workstations, productTypes })}
                initial={TRIGGER_INITIAL}
                submitLabel={__('Create')}
                cancelHref="/admin/quality-control-triggers"
            />
        </div>
    );
}

QualityControlTriggerCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
