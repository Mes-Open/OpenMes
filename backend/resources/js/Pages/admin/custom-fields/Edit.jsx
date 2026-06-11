import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import DefinitionForm from '../../../components/DefinitionForm';
import { __ } from '../../../lib/i18n';

export default function CustomFieldEdit() {
    const { definition, entities = [], types = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('Edit :label', { label: definition.label })} />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">{__('Edit Custom Field')}</h1>
            <DefinitionForm
                action={`/admin/custom-fields/${definition.id}`}
                method="put"
                entities={entities}
                types={types}
                initial={{
                    entity_type: definition.entity_type ?? '',
                    key: definition.key ?? '',
                    label: definition.label ?? '',
                    type: definition.type ?? '',
                    required: !!definition.required,
                    is_active: !!definition.is_active,
                    position: definition.position ?? 0,
                    config: {
                        options: definition.config?.options ?? [],
                        min: definition.config?.min ?? '',
                        max: definition.config?.max ?? '',
                    },
                }}
                submitLabel={__('Save Changes')}
            />
        </div>
    );
}

CustomFieldEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
