import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { unitAliasFields, unitAliasInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function UnitAliasCreate() {
    const { units = [] } = usePage().props;

    return (
        <>
            <Head title={__('New Unit Alias')} />
            <ResourceForm
                title={__('New Unit Alias')}
                action="/admin/unit-aliases"
                fields={unitAliasFields(units)}
                initial={unitAliasInitial()}
                cancelHref="/admin/unit-aliases"
            />
        </>
    );
}

UnitAliasCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
