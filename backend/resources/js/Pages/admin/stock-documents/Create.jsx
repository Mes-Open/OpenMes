import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import StockDocumentForm from './StockDocumentForm';
import { __ } from '../../../lib/i18n';

/**
 * Standalone manual stock-document entry. The list creates in a modal over the
 * same form; this page stays for deep links and for anyone who wants the room.
 */
export default function StockDocumentCreate({ warehouses = [], materials = [], productTypes = [], types = [] }) {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Stock Document')} />

            <Link href="/admin/stock-documents" className="text-[12px] text-om-muted hover:text-om-ink">
                ‹ {__('Stock Documents')}
            </Link>
            <h1 className="text-3xl font-bold text-om-ink mt-2 mb-6">{__('New Stock Document')}</h1>

            <StockDocumentForm
                warehouses={warehouses}
                materials={materials}
                productTypes={productTypes}
                types={types}
            />
        </div>
    );
}

StockDocumentCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
