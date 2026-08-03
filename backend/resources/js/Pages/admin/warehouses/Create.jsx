import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { warehouseFields } from './fields';
import { __ } from '../../../lib/i18n';

export default function WarehouseCreate() {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Warehouse')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('New Warehouse')}</h1>
            <ResourceForm
                action="/admin/warehouses"
                method="post"
                fields={warehouseFields()}
                initial={{
                    code: '',
                    name: '',
                    kind: 'mixed',
                    description: '',
                    erp_code: '',
                    is_default: false,
                    is_active: true,
                }}
                submitLabel={__('Create')}
                cancelHref="/admin/warehouses"
            />
        </div>
    );
}

WarehouseCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
