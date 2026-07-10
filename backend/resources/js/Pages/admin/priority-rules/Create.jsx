import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { priorityRuleFields } from './fields';
import { __ } from '../../../lib/i18n';

export default function PriorityRuleCreate() {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Priority Rule')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('New Priority Rule')}</h1>
            <ResourceForm
                action="/admin/priority-rules"
                method="post"
                fields={priorityRuleFields()}
                initial={{ name: '', field_source: '', condition_type: '', condition_value: '', condition_value_max: '', points: 0, sort_order: 0, is_active: true }}
                submitLabel={__('Create')}
                cancelHref="/admin/priority-rules"
            />
        </div>
    );
}

PriorityRuleCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
