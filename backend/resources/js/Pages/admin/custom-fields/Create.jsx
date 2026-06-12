import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import DefinitionForm from '../../../components/DefinitionForm';
import { __ } from '../../../lib/i18n';

export default function CustomFieldCreate() {
    const { entities = [], types = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Custom Field')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('New Custom Field')}</h1>
            <DefinitionForm
                action="/admin/custom-fields"
                method="post"
                entities={entities}
                types={types}
                initial={{
                    entity_type: '', key: '', label: '', type: '',
                    required: false, is_active: true, position: 0,
                    config: { options: [] },
                }}
                submitLabel={__('Create')}
            />
        </div>
    );
}

CustomFieldCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
