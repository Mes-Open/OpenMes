import { Head, router, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import { __ } from '../../../lib/i18n';

const STATUS_OPTIONS = [
    { value: 'ACTIVE', label: '生效' },
    { value: 'RETIRED', label: '停用' },
];

function standardInitial(record) {
    if (!record) {
        return {
            name: '',
            code: '',
            version: 'V1',
            status: 'ACTIVE',
            customer_id: '',
            product_type_id: '',
            effective_from: '',
            effective_to: '',
            moisture_min: '',
            moisture_max: '',
            sensory_aroma: '',
            sensory_taste: '',
            sensory_liquor: '',
            blend_tolerance: '',
            foreign_matter_required: true,
            requires_external_lab: true,
            net_weight_min: '',
            net_weight_max: '',
            packaging_requirement: '',
            notes: '',
        };
    }

    return {
        name: record.name ?? '',
        code: record.code ?? '',
        version: record.version ?? 'V1',
        status: record.status ?? 'ACTIVE',
        customer_id: record.customer_id ?? '',
        product_type_id: record.product_type_id ?? '',
        effective_from: record.effective_from ?? '',
        effective_to: record.effective_to ?? '',
        moisture_min: record.moisture_min ?? '',
        moisture_max: record.moisture_max ?? '',
        sensory_aroma: record.sensory_aroma ?? '',
        sensory_taste: record.sensory_taste ?? '',
        sensory_liquor: record.sensory_liquor ?? '',
        blend_tolerance: record.blend_tolerance ?? '',
        foreign_matter_required: !!record.foreign_matter_required,
        requires_external_lab: !!record.requires_external_lab,
        net_weight_min: record.net_weight_min ?? '',
        net_weight_max: record.net_weight_max ?? '',
        packaging_requirement: record.packaging_requirement ?? '',
        notes: record.notes ?? '',
    };
}

function range(min, max, unit) {
    if (min === null && max === null) return '—';
    if (min !== null && max !== null) return `${min} – ${max}${unit ? ` ${unit}` : ''}`;
    return min !== null ? `≥ ${min}${unit ? ` ${unit}` : ''}` : `≤ ${max}${unit ? ` ${unit}` : ''}`;
}

export default function CustomerFlavorStandardsIndex() {
    const { rows = [], customers = [], productTypes = [], basePath } = usePage().props;
    const drawer = useResourceDrawer();

    const fields = useMemo(() => [
        { name: 'name', label: '标准名称', required: true, placeholder: '例如：茉莉绿茶客户风味标准' },
        { name: 'code', label: '标准编号', required: true, placeholder: '例如：HT-CFS-DEMO-A-JASMINE-V1' },
        { name: 'version', label: '版本', required: true },
        { name: 'status', label: '状态', type: 'select', options: STATUS_OPTIONS },
        { name: 'customer_id', label: '客户', type: 'select', required: true, options: customers },
        {
            name: 'product_type_id',
            label: '适用产品',
            type: 'select',
            options: [{ value: '', label: '该客户全部产品' }, ...productTypes],
            help: '选择具体产品时优先生效；留空表示该客户的产品族通用标准。',
        },
        { name: 'effective_from', label: '生效日期', type: 'date' },
        { name: 'effective_to', label: '失效日期', type: 'date' },
        { name: 'moisture_min', label: '水分下限（%）', type: 'number', step: '0.001' },
        { name: 'moisture_max', label: '水分上限（%）', type: 'number', step: '0.001' },
        { name: 'sensory_aroma', label: '香气要求', placeholder: '例如：茉莉花香鲜灵、持久' },
        { name: 'sensory_taste', label: '滋味要求', placeholder: '例如：鲜爽、回甘、无苦涩' },
        { name: 'sensory_liquor', label: '汤色要求', placeholder: '例如：黄绿明亮' },
        { name: 'blend_tolerance', label: '拼配比例允许偏差（±%）', type: 'number', step: '0.001' },
        { name: 'foreign_matter_required', label: '要求无异物（不得检出）', type: 'checkbox' },
        { name: 'requires_external_lab', label: '要求第三方外检（农残/重金属/微生物）', type: 'checkbox' },
        { name: 'net_weight_min', label: '净含量下限（g）', type: 'number', step: '0.001' },
        { name: 'net_weight_max', label: '净含量上限（g）', type: 'number', step: '0.001' },
        { name: 'packaging_requirement', label: '包装要求', type: 'textarea' },
        { name: 'notes', label: '备注', type: 'textarea' },
    ], [customers, productTypes]);

    const activeCount = rows.filter((row) => row.status === 'ACTIVE').length;
    const customerCount = new Set(rows.map((row) => row.customer_id)).size;
    const productSpecificCount = rows.filter((row) => row.product_type_id).length;

    const columns = [
        { key: 'name', label: '标准名称', className: 'font-medium text-om-ink', filter: 'text' },
        { key: 'code', label: '标准编号', className: 'font-mono text-om-muted', filter: 'text' },
        { key: 'customer_name', label: '客户', value: (r) => r.customer_name ?? '', filter: 'text' },
        { key: 'product_name', label: '适用产品', value: (r) => r.product_name ?? '', filter: 'text' },
        { key: 'version', label: '版本', filter: 'text' },
        {
            key: 'moisture', label: '水分', value: (r) => r.moisture_max ?? -1,
            render: (r) => range(r.moisture_min, r.moisture_max, '%'), filter: false,
        },
        {
            key: 'sensory', label: '感官要求',
            value: (r) => [r.sensory_aroma, r.sensory_taste, r.sensory_liquor].filter(Boolean).join('；'),
            render: (r) => {
                const parts = [r.sensory_aroma, r.sensory_taste, r.sensory_liquor].filter(Boolean);
                return parts.length ? <span className="text-om-muted">{parts.join('；')}</span> : <span className="text-om-faint">—</span>;
            },
            filter: 'text',
        },
        {
            key: 'effective', label: '有效期',
            value: (r) => [r.effective_from, r.effective_to].filter(Boolean).join(' → '),
            render: (r) => `${r.effective_from ?? '不限'} → ${r.effective_to ?? '长期'}`,
            filter: false,
        },
        {
            key: 'status', label: '状态', value: (r) => r.status,
            render: (r) => (
                <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ${r.status === 'ACTIVE' ? 'bg-emerald-100 text-emerald-800' : 'bg-om-chip text-om-muted'}`}>
                    {r.status === 'ACTIVE' ? '生效' : '停用'}
                </span>
            ),
            filter: 'select', options: STATUS_OPTIONS,
        },
    ];

    const actions = (row) => [
        { label: '编辑', onClick: () => drawer.edit(row) },
        {
            label: row.status === 'ACTIVE' ? '停用' : '启用',
            onClick: () => router.post(`${basePath}/${row.id}/toggle-status`, {}, { preserveScroll: true }),
        },
        {
            label: '删除',
            className: 'text-om-blocked hover:underline',
            confirm: { title: `删除客户风味标准“${row.name}”？`, confirmLabel: '删除' },
            onClick: () => router.delete(`${basePath}/${row.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title="客户风味标准" />
            <div className="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                {[
                    { label: '生效标准', value: activeCount, hint: '当前可被 COA 引用的客户标准' },
                    { label: '覆盖客户', value: customerCount, hint: '已建立标准的客户数' },
                    { label: '产品专属', value: productSpecificCount, hint: '绑定具体产品，优先于通用标准' },
                ].map((card) => (
                    <div key={card.label} className="rounded-om-md border border-om-line bg-om-bg px-4 py-3">
                        <div className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{card.label}</div>
                        <div className="mt-1 text-[24px] font-semibold tracking-[-0.02em] text-om-ink">{card.value}</div>
                        <div className="mt-1 text-xs text-om-muted">{card.hint}</div>
                    </div>
                ))}
            </div>

            <ResourceTable
                shape="customer_flavor_standards"
                rows={rows}
                title="客户风味标准"
                onCreate={drawer.create}
                createLabel="新建客户标准"
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText="尚未建立客户风味标准。先为客户和产品定义可放行的风味规格。"
            />

            <ResourceFormDrawer
                {...drawer.props}
                action={basePath}
                fields={fields}
                initial={standardInitial}
                title={{ create: '新建客户风味标准', edit: '编辑客户风味标准' }}
                subtitle="COA 生成时冻结此标准；改标准不会改动已签发版本。"
                submitLabel={{ create: '创建', edit: '保存' }}
                width={640}
            />
        </>
    );
}

CustomerFlavorStandardsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
