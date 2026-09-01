import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { priorityRuleFields, priorityRuleInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function PriorityRuleCreate({ basePath }) {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Priority Rule')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('New Priority Rule')}</h1>
            <ResourceForm
                action={basePath}
                method="post"
                fields={priorityRuleFields()}
                initial={priorityRuleInitial(null)}
                submitLabel={__('Create')}
                cancelHref={basePath}
            />
        </div>
    );
}

PriorityRuleCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
