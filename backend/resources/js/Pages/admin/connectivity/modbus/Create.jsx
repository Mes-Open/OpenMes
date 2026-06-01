import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../../../layouts/AppLayout';
import ModbusConnectionForm from './ModbusConnectionForm';
import { __ } from '../../../../lib/i18n';

export default function ModbusCreate() {
    return (
        <>
            <Head title={__('New Modbus Connection')} />
            <div className="p-6 max-w-2xl">
                <div className="mb-6">
                    <Link
                        href="/admin/connectivity/modbus"
                        className="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 flex items-center gap-1"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        {__('Back to Modbus Connections')}
                    </Link>
                    <h1 className="mt-3 text-2xl font-bold text-gray-900 dark:text-white">{__('New Modbus Connection')}</h1>
                </div>

                <ModbusConnectionForm
                    action="/admin/connectivity/modbus"
                    method="post"
                    submitLabel={__('Create Connection')}
                    cancelHref="/admin/connectivity/modbus"
                />
            </div>
        </>
    );
}

ModbusCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
