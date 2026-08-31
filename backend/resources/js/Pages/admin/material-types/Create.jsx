import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { materialTypeFields } from './fields';
import { __ } from '../../../lib/i18n';

export default function MaterialTypeCreate() {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Material Type')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('New Material Type')}</h1>
            <ResourceForm
                action="/admin/material-types"
                method="post"
                fields={materialTypeFields()}
                initial={{ code: '', name: '' }}
                submitLabel={__('Create')}
                cancelHref="/admin/material-types"
            />
        </div>
    );
}

MaterialTypeCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
