import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { customerFields } from './fields';
import { __ } from '../../../lib/i18n';

export default function CustomerCreate() {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Customer')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('New Customer')}</h1>
            <ResourceForm
                action="/admin/customers"
                method="post"
                fields={customerFields()}
                initial={{ name: '', code: '', tier: 'bronze', payment_score: 0, notes: '', is_active: true }}
                submitLabel={__('Create')}
                cancelHref="/admin/customers"
            />
        </div>
    );
}

CustomerCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
