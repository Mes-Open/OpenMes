import { Head, router, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import useConfirm from '../../../components/useConfirm';
import { formatDateTime } from '../../../lib/i18n';

const CRITERIA_EXAMPLE = '水分|测量|%|5|8|必填\n感官-香气|感官|—|||必填\n无异物|合格|—|||必填';

function criteriaInitial(record) {
    return {
        name: record?.name ?? '',
        description: record?.description ?? '',
        material_id: record?.material_name ? String(record.material_id ?? '') : '',
        material_type_id: record?.material_type_name ? String(record.material_type_id ?? '') : '',
        criteria_text: record?.criteria_text ?? CRITERIA_EXAMPLE,
    };
}

export default function InspectionPlansIndex() {
    const { rows = [], materials = [], materialTypes = [], basePath, filters = {} } = usePage().props;
    const drawer = useResourceDrawer();
    const { confirm, dialog } = useConfirm();

    const stats = useMemo(() => ({
        total: rows.length,
        active: rows.filter((row) => row.is_active).length,
        drafts: rows.filter((row) => row.is_draft).length,
        covered: new Set(rows.filter((row) => row.is_active).map((row) => row.scope)).size,
    }), [rows]);

    const fields = useMemo(() => [
        { name: 'name', label: '计划名称', required: true, placeholder: '例如：贵州绿茶毛茶来料检验' },
        { name: 'description', label: '说明', type: 'textarea', placeholder: '检验依据、抽样要求、异常处理' },
        {
            name: 'material_id',
            label: '限定到具体原料',
            type: 'select',
            options: [{ value: '', label: '不限原料（按类别或通用）' }, ...materials],
            help: '与“限定到原料类别”二选一；具体原料优先匹配。',
        },
        {
            name: 'material_type_id',
            label: '限定到原料类别',
            type: 'select',
            options: [{ value: '', label: '不限类别' }, ...materialTypes],
            help: '例如“茶叶原料/包装材料”；具体原料为空时才按类别匹配。',
        },
        {
            name: 'criteria_text',
            label: '检验项目',
            type: 'textarea',
            required: true,
            help: '每行一条：项目|类型|单位|下限|上限|必填。类型可用 感官 / 测量 / 功能 / 合格。',
            placeholder: CRITERIA_EXAMPLE,
        },
    ], [materials, materialTypes]);

    const publish = (row) => confirm(
        {
            title: `发布“${row.name}” V${row.version}？`,
            body: '发布后该版本立即生效，旧版本自动停用；已产生的检验记录保持不变。',
            confirmLabel: '发布',
            destructive: false,
        },
        () => router.post(`${basePath}/${row.id}/publish`, {}, { preserveScroll: true }),
    );

    const remove = (row) => confirm(
        {
            title: `删除“${row.name}” V${row.version}？`,
            body: row.inspections_count > 0
                ? '该版本已有检验记录，系统会拒绝删除；请改为派生新版本。'
                : '删除后不可恢复。',
            confirmLabel: '删除',
        },
        () => router.delete(`${basePath}/${row.id}`, { preserveScroll: true }),
    );

    const search = (value) => router.get(basePath, { q: value }, { preserveState: true, replace: true });

    return (
        <>
            <Head title="来料检验计划" />
            {dialog}
            <div className="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-4">
                {[
                    { label: '计划版本', value: stats.total, hint: '含草稿与历史版本' },
                    { label: '当前生效', value: stats.active, hint: '来料检验可直接选用' },
                    { label: '待发布草稿', value: stats.drafts, hint: '编辑后需发布才生效' },
                    { label: '覆盖范围', value: stats.covered, hint: '生效计划覆盖的原料/类别' },
                ].map((card) => (
                    <div key={card.label} className="rounded-om-md border border-om-line bg-om-bg px-4 py-3">
                        <div className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{card.label}</div>
                        <div className="mt-1 text-[24px] font-semibold tracking-[-0.02em] text-om-ink">{card.value}</div>
                        <div className="mt-1 text-xs text-om-muted">{card.hint}</div>
                    </div>
                ))}
            </div>

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-om-ink">来料检验计划</h1>
                    <p className="mt-1 text-sm text-om-muted">原料入厂时按计划抽样、记录、判定；发布后的版本不可改，只能派生新草稿。</p>
                </div>
                <div className="flex gap-2">
                    <input
                        defaultValue={filters.q ?? ''}
                        onKeyDown={(event) => { if (event.key === 'Enter') search(event.currentTarget.value); }}
                        placeholder="搜索计划名称…"
                        className="w-48 rounded border border-om-line bg-om-card px-3 py-2 text-sm text-om-ink outline-none focus:border-om-accent"
                    />
                    <button type="button" onClick={drawer.create} className="btn-touch btn-primary text-sm">新建计划</button>
                </div>
            </div>

            <div className="overflow-hidden rounded-om-md border border-om-line bg-om-card">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[860px] text-sm">
                        <thead>
                            <tr className="border-b border-om-line bg-om-bg text-left text-xs text-om-muted">
                                <th className="px-4 py-3 font-semibold">计划名称</th>
                                <th className="px-3 py-3 font-semibold">适用范围</th>
                                <th className="px-3 py-3 font-semibold">版本</th>
                                <th className="px-3 py-3 font-semibold">项目</th>
                                <th className="px-3 py-3 font-semibold">状态</th>
                                <th className="px-3 py-3 font-semibold">已检验</th>
                                <th className="px-3 py-3 font-semibold">发布时间</th>
                                <th className="px-4 py-3 text-right font-semibold">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.id} className="border-b border-om-line2/60 align-top">
                                    <td className="px-4 py-3">
                                        <div className="font-semibold text-om-ink">{row.name}</div>
                                        {row.description && <div className="mt-1 line-clamp-2 text-xs text-om-muted">{row.description}</div>}
                                    </td>
                                    <td className="px-3 py-3 text-xs text-om-muted">{row.scope}</td>
                                    <td className="px-3 py-3 font-mono text-xs text-om-ink">V{row.version}</td>
                                    <td className="px-3 py-3 text-xs text-om-ink">{row.criteria_count} 项</td>
                                    <td className="px-3 py-3">
                                        {row.is_active ? (
                                            <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">生效</span>
                                        ) : row.is_draft ? (
                                            <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">草稿</span>
                                        ) : (
                                            <span className="rounded-full bg-om-chip px-2 py-0.5 text-[11px] font-semibold text-om-muted">历史版本</span>
                                        )}
                                    </td>
                                    <td className="px-3 py-3 text-xs text-om-muted">{row.inspections_count} 次</td>
                                    <td className="px-3 py-3 text-xs text-om-muted">{row.published_at ? formatDateTime(row.published_at) : '—'}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end gap-3 text-xs">
                                            <button type="button" onClick={() => drawer.edit(row)} className="font-bold text-om-accent hover:underline">编辑</button>
                                            {row.is_draft && <button type="button" onClick={() => publish(row)} className="font-bold text-om-accent hover:underline">发布</button>}
                                            <button type="button" onClick={() => remove(row)} className="font-bold text-om-blocked hover:underline">删除</button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {rows.length === 0 && (
                                <tr><td colSpan={8} className="px-4 py-10 text-center text-sm text-om-muted">还没有来料检验计划，新建一条草稿后发布。</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <ResourceFormDrawer
                {...drawer}
                action={basePath}
                fields={fields}
                initial={criteriaInitial}
                title={{ create: '新建来料检验计划', edit: '编辑来料检验计划' }}
                subtitle="草稿可反复编辑；已发布版本编辑会派生新草稿，发布后旧版本自动停用。"
                submitLabel={{ create: '保存草稿', edit: '保存' }}
                width={640}
            />
        </>
    );
}

InspectionPlansIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
