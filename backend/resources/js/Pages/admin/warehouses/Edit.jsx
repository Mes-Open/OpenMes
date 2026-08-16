import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { warehouseFields } from './fields';
import { __ } from '../../../lib/i18n';

export default function WarehouseEdit({ warehouse }) {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`${__('Edit')} ${warehouse.name}`} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('Edit Warehouse')}</h1>
            <ResourceForm
                action={`/admin/warehouses/${warehouse.id}`}
                method="put"
                fields={warehouseFields()}
                initial={{
                    code: warehouse.code ?? '',
                    name: warehouse.name ?? '',
                    kind: warehouse.kind ?? 'mixed',
                    description: warehouse.description ?? '',
                    erp_code: warehouse.erp_code ?? '',
                    is_default: !!warehouse.is_default,
                    is_active: !!warehouse.is_active,
                }}
                submitLabel={__('Save Changes')}
                cancelHref="/admin/warehouses"
            />
        </div>
    );
}

WarehouseEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
