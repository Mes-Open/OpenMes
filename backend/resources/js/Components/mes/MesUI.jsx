import React from 'react';
import PageTitle from '../../components/PageTitle';

/**
 * MES 工业台：面向国内制造企业的高密度工作台。
 * 所有仓储作业页共用同一套标题、指标条、状态标签和分页，
 * 保持“表格优先、状态明确、操作路径短”的交互习惯。
 */

export function MesPage({ children, className = '' }) {
    return (
        <div className={`mes-page ${className}`}>
            <div className="mes-container">{children}</div>
        </div>
    );
}

export function MesHeader({ breadcrumb = [], title, tag, description, meta, actions }) {
    return (
        <div className="mes-page-head">
            <div className="min-w-0">
                {breadcrumb.length > 0 && (
                    <PageTitle>
                        <div className="mes-head-trail">
                            {breadcrumb.map((item, index) => (
                                <React.Fragment key={`${item}-${index}`}>
                                    {index > 0 && <span className="sep">/</span>}
                                    <span>{item}</span>
                                </React.Fragment>
                            ))}
                        </div>
                    </PageTitle>
                )}
                <div className="mes-title-row">
                    <h1 className="mes-title">{title}</h1>
                    {tag && <MesTag tone={tag.tone ?? 'blue'}>{tag.label}</MesTag>}
                </div>
                {description && <p className="mes-subtitle">{description}</p>}
                {meta && <div className="mt-1 text-[10px] text-[var(--mes-faint)]">{meta}</div>}
            </div>
            {actions && <div className="mes-actions">{actions}</div>}
        </div>
    );
}

export function MesStats({ items }) {
    return (
        <div className="mes-stat-grid">
            {items.map((item) => (
                <div
                    key={item.label}
                    className="mes-stat"
                    style={{ '--mes-stat-tone': item.tone ?? 'var(--mes-blue)' }}
                >
                    <div className="mes-stat-label">
                        <span>{item.label}</span>
                        {item.extra}
                    </div>
                    <div className="mes-stat-value">{item.value}</div>
                    {item.sub && <div className="mes-stat-sub" title={item.sub}>{item.sub}</div>}
                </div>
            ))}
        </div>
    );
}

export function MesTag({ tone = 'gray', children }) {
    return <span className={`mes-tag mes-tag-${tone}`}>{children}</span>;
}

export function MesSection({ title, meta, actions, children, footer }) {
    return (
        <section className="mes-panel">
            {(title || actions) && (
                <div className="mes-section-head">
                    <div className="flex min-w-0 items-center gap-2">
                        {title && <h2 className="mes-section-title">{title}</h2>}
                        {meta && <span className="mes-section-meta">{meta}</span>}
                    </div>
                    {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
                </div>
            )}
            {children}
            {footer}
        </section>
    );
}

export function MesPager({ page, onPage, total, pageSize = 20, label = '条' }) {
    const pages = Math.max(1, Math.ceil((total ?? 0) / pageSize));
    const safePage = Math.min(Math.max(1, page), pages);
    const from = total ? (safePage - 1) * pageSize + 1 : 0;
    const to = total ? Math.min(safePage * pageSize, total) : 0;
    const nums = pageNumbers(pages, safePage);
    return (
        <div className="mes-footer">
            <div className="flex items-center gap-3">
                <span>共 <b className="font-mono text-[var(--mes-ink)]">{total ?? 0}</b> {label}</span>
                <span className="text-[var(--mes-faint)]">当前 {from}-{to}</span>
            </div>
            <div className="mes-pager">
                <button type="button" disabled={safePage <= 1} onClick={() => onPage(safePage - 1)} className="disabled:opacity-40">上一页</button>
                {nums.map((num, index) => (
                    num === '…' ? (
                        <span key={`gap-${index}`} className="px-1 text-[var(--mes-faint)]">…</span>
                    ) : (
                        <button key={num} type="button" data-active={num === safePage} onClick={() => onPage(num)}>{num}</button>
                    )
                ))}
                <button type="button" disabled={safePage >= pages} onClick={() => onPage(safePage + 1)} className="disabled:opacity-40">下一页</button>
                <span className="ml-1 text-[var(--mes-faint)]">{safePage}/{pages}</span>
            </div>
        </div>
    );
}

function pageNumbers(total, current) {
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
    if (current <= 4) return [1, 2, 3, 4, 5, '…', total];
    if (current >= total - 3) return [1, '…', total - 4, total - 3, total - 2, total - 1, total];
    return [1, '…', current - 1, current, current + 1, '…', total];
}

export function useClientPage(rows, pageSize = 20) {
    const [page, setPage] = React.useState(1);
    React.useEffect(() => { setPage(1); }, [rows.length]);
    const start = (page - 1) * pageSize;
    const visible = rows.slice(start, start + pageSize);
    return { page, setPage, visible, pageSize };
}
