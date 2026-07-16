import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { priorityRuleFields } from './fields';
import { __ } from '../../../lib/i18n';

export default function PriorityRuleEdit({ priorityRule }) {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('Edit Priority Rule')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('Edit Priority Rule')}</h1>
            <ResourceForm
                action={`/admin/priority-rules/${priorityRule.id}`}
                method="put"
                fields={priorityRuleFields()}
                initial={{
                    name: priorityRule.name ?? '',
                    field_source: priorityRule.field_source ?? '',
                    condition_type: priorityRule.condition_type ?? '',
                    condition_value: priorityRule.condition_value ?? '',
                    condition_value_max: priorityRule.condition_value_max ?? '',
                    points: priorityRule.points ?? 0,
                    sort_order: priorityRule.sort_order ?? 0,
                    is_active: !!priorityRule.is_active,
                }}
                submitLabel={__('Save Changes')}
                cancelHref="/admin/priority-rules"
            />
        </div>
    );
}

PriorityRuleEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
