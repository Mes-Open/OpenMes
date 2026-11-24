import { Head, usePage } from '@inertiajs/react';
import { Icon } from '@openmes/ui';
import { formatDateTime } from '../../lib/i18n';

const STATE_META = {
    valid: {
        label: '有效',
        eyebrow: 'VALID',
        icon: 'shield-check',
        text: 'text-om-running',
        bg: 'bg-om-running-bg/40',
        border: 'border-om-running/25',
        description: '证书已签发，电子签名与记录哈希一致。',
        detail: '可用于客户收货、销售复核和批次追溯；下载的 PDF 与本页二维码同源。',
    },
    void: {
        label: '已作废',
        eyebrow: 'VOID',
        icon: 'shield-x',
        text: 'text-om-blocked',
        bg: 'bg-om-blocked-bg/40',
        border: 'border-om-blocked/25',
        description: '签发方已作废此证书，不再作为对外文件使用。',
        detail: '旧签名不再作为有效证据；如需继续交易，请使用重签后的版本。',
    },
    revoked: {
        label: '链接已失效',
        eyebrow: 'REVOKED',
        icon: 'link-2-off',
        text: 'text-om-blocked',
        bg: 'bg-om-blocked-bg/40',
        border: 'border-om-blocked/25',
        description: '此验真链接已停用，不能证明当前证书状态。',
        detail: '证书本身是否有效，请以签发方提供的最新验真链接为准。',
    },
    invalid: {
        label: '签名异常',
        eyebrow: 'INVALID',
        icon: 'alert-triangle',
        text: 'text-om-blocked',
        bg: 'bg-om-blocked-bg/40',
        border: 'border-om-blocked/25',
        description: '签名记录与签发时的哈希不一致，请停止使用。',
        detail: '不要依据这张证书收货或放行；请联系质量负责人核对原件。',
    },
    unavailable: {
        label: '尚未生效',
        eyebrow: 'NOT YET ISSUED',
        icon: 'clock',
        text: 'text-om-downtime',
        bg: 'bg-om-downtime-bg/40',
        border: 'border-om-downtime/25',
        description: '这张证书尚未完成签发，不能对外使用。',
        detail: '请等待 QA 完成批准与签发后再验真。',
    },
    not_found: {
        label: '未找到',
        eyebrow: 'NOT FOUND',
        icon: 'search-x',
        text: 'text-om-muted',
        bg: 'bg-om-chip/50',
        border: 'border-om-line',
        description: '这个链接不完整，或对应的证书不存在。',
        detail: '请核对链接是否被截断；仍无法打开时，向签发方索取新的验真链接。',
    },
};

export default function CoaVerification() {
    const { state = 'not_found', coa, token, signatures = [], proof_hash: proofHash, pdf_url: pdfUrl } = usePage().props;
    const meta = STATE_META[state] ?? STATE_META.not_found;
    const showFacts = state !== 'not_found' && coa;
    const showSignatures = state === 'valid' || state === 'invalid';
    const showProof = state === 'valid' || state === 'invalid';

    return (
        <>
            <Head title={`COA 验真${coa?.number ? ` · ${coa.number}` : ''}`}>
                <meta name="robots" content="noindex,nofollow" />
                <style>{`
                    @page { size: A4 portrait; margin: 14mm; }
                    @media print {
                        .no-print { display: none !important; }
                        .print-avoid-break { break-inside: avoid; }
                        body { background: #fff !important; }
                        .verify-shell { min-height: 0 !important; }
                    }
                `}</style>
            </Head>

            <div className="verify-shell min-h-screen bg-om-page text-om-ink">
                <header className="no-print border-b border-om-line bg-om-bg">
                    <div className="mx-auto flex max-w-[980px] items-center justify-between gap-4 px-4 py-4 sm:px-6">
                        <div className="flex min-w-0 items-center gap-2.5">
                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-om-sm bg-om-ink text-[15px] font-bold text-om-card">逅</div>
                            <div className="min-w-0">
                                <div className="truncate text-sm font-bold tracking-tight text-om-ink">逅唐茶业</div>
                                <div className="mt-0.5 font-mono text-[10px] tracking-[0.08em] text-om-faint">HOUTANG · OPENMES</div>
                            </div>
                        </div>
                        <div className="text-right">
                            <div className="text-[11px] font-bold tracking-[0.08em] text-om-ink">分析证书验真</div>
                            <div className="mt-0.5 text-[10px] text-om-faint">公开页 · 无需登录</div>
                        </div>
                    </div>
                </header>

                <main className="mx-auto w-full max-w-[980px] px-4 pb-16 pt-6 sm:px-6">
                    <section className={`print-avoid-break rounded-om-sm border px-5 py-5 sm:px-6 sm:py-6 ${meta.bg} ${meta.border}`}>
                        <div className="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                            <div className="min-w-0">
                                <div className={`inline-flex items-center gap-1.5 text-[10px] font-bold tracking-[0.12em] ${meta.text}`}>
                                    <Icon name={meta.icon} size={13} />
                                    {meta.eyebrow}
                                </div>
                                <h1 className={`mt-1 text-[36px] font-bold leading-none tracking-tight sm:text-[44px] ${meta.text}`}>{meta.label}</h1>
                                <p className="mt-2 text-sm font-bold leading-6 text-om-ink">{meta.description}</p>
                                <p className="mt-1 max-w-[560px] text-xs leading-5 text-om-muted">{meta.detail}</p>
                            </div>
                            <div className="min-w-0 border-t border-om-line2 pt-3 lg:min-w-[290px] lg:border-l lg:border-t-0 lg:pl-5 lg:pt-0 lg:text-right">
                                <div className="text-[10px] font-bold tracking-[0.08em] text-om-faint">COA 编号</div>
                                <div className="mt-1 break-all font-mono text-[12px] font-bold leading-4 text-om-ink">{coa?.number ?? '—'}</div>
                                <div className="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-om-faint lg:justify-end">
                                    <span>版本 <span className="font-mono text-om-muted">{coa?.version ?? '—'}</span></span>
                                    <span>签发 <span className="font-mono text-om-muted">{coa?.issued_at ? formatDateTime(coa.issued_at) : '—'}</span></span>
                                </div>
                            </div>
                        </div>
                    </section>

                    {state === 'not_found' ? (
                        <section className="print-avoid-break mt-4 rounded-om-sm border border-om-line bg-om-card px-4 py-10 text-center">
                            <p className="text-sm font-bold text-om-ink">请使用完整的验真链接</p>
                            <p className="mx-auto mt-1 max-w-[480px] text-xs leading-5 text-om-muted">
                                链接中的随机令牌缺失或被截断时，系统不会返回任何证书信息。不要根据陌生链接判断证书真假。
                            </p>
                        </section>
                    ) : (
                        <>
                            {(state === 'void' || state === 'revoked') && (
                                <div className="print-avoid-break mt-4 rounded-om-sm border border-om-line bg-om-card px-4 py-4">
                                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                                        <h2 className="text-sm font-bold text-om-ink">{state === 'void' ? '作废原因' : '链接停用原因'}</h2>
                                        <span className="font-mono text-[10px] text-om-faint">
                                            {state === 'void' && coa?.voided_at ? formatDateTime(coa.voided_at) : token?.revoked_at ? formatDateTime(token.revoked_at) : '—'}
                                        </span>
                                    </div>
                                    <p className="mt-2 whitespace-pre-line text-xs leading-5 text-om-muted">
                                        {state === 'void' ? (coa?.void_reason || '签发方未提供作废说明。') : (token?.revoke_reason || '此链接已被签发方停用。')}
                                    </p>
                                    {state === 'void' && (
                                        <p className="mt-2 text-[11px] text-om-faint">
                                            {coa?.reissue_version ? `已建立重签版本 ${coa.reissue_version}，请以新版本验真链接为准。` : '重签版本与新的验真链接请向签发方确认。'}
                                        </p>
                                    )}
                                </div>
                            )}

                            {showFacts && <Facts coa={coa} />}

                            {showSignatures && (
                                <section className="print-avoid-break mt-4 overflow-hidden rounded-om-sm border border-om-line bg-om-card">
                                    <div className="flex flex-wrap items-baseline justify-between gap-2 border-b border-om-line2 px-4 py-3">
                                        <div>
                                            <h2 className="text-sm font-bold text-om-ink">签名记录</h2>
                                            <p className="mt-0.5 text-[11px] text-om-muted">姓名、角色、含义与时间均来自签发时的电子签名。</p>
                                        </div>
                                        <span className={`inline-flex items-center gap-1 text-[11px] font-bold ${state === 'valid' ? 'text-om-running' : 'text-om-blocked'}`}>
                                            <Icon name={state === 'valid' ? 'shield-check' : 'alert-triangle'} size={13} />
                                            {state === 'valid' ? '哈希校验通过' : '存在校验异常'}
                                        </span>
                                    </div>
                                    <div className="grid gap-px bg-om-line2 sm:grid-cols-3">
                                        {signatures.map((signature) => (
                                            <SignatureCard key={`${signature.meaning}-${signature.hash}`} signature={signature} />
                                        ))}
                                    </div>
                                </section>
                            )}

                            {(state === 'void' || state === 'revoked') && (
                                <div className="print-avoid-break mt-4 rounded-om-sm border border-om-line bg-om-card px-4 py-3 text-xs leading-5 text-om-muted">
                                    此页不展示旧版签名详情；历史签名记录保留在签发方系统内，需核对时请联系质量负责人。
                                </div>
                            )}

                            <div className="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
                                <section className="print-avoid-break rounded-om-sm border border-om-line bg-om-card px-4 py-4">
                                    <h2 className="text-sm font-bold text-om-ink">如何核对</h2>
                                    <ol className="mt-2 space-y-1.5 text-xs leading-5 text-om-muted">
                                        <li><span className="mr-1.5 font-mono text-[10px] text-om-faint">01</span>扫描 COA 上的二维码，或打开当前验真链接。</li>
                                        <li><span className="mr-1.5 font-mono text-[10px] text-om-faint">02</span>核对 COA 编号、批次、客户与签发时间。</li>
                                        <li><span className="mr-1.5 font-mono text-[10px] text-om-faint">03</span>签名短哈希应与 PDF 页脚一致；异常时停止使用。</li>
                                    </ol>
                                </section>

                                {showProof && (
                                    <section className="print-avoid-break rounded-om-sm border border-om-line bg-om-card px-4 py-4">
                                        <h2 className="text-sm font-bold text-om-ink">文件指纹</h2>
                                        <dl className="mt-2 space-y-2">
                                            <HashRow label="签名短哈希" value={proofHash || '—'} />
                                            <HashRow label="验真令牌" value={token?.short_hash || '—'} />
                                        </dl>
                                        <p className="mt-2 text-[10px] leading-4 text-om-faint">公开页只展示哈希前缀，不显示内部用户、工单、成本或供应商信息。</p>
                                    </section>
                                )}
                            </div>

                            {(state === 'valid' || state === 'void') && (
                                <div className="no-print mt-4 flex flex-wrap items-center justify-between gap-3">
                                    <p className="text-[11px] leading-5 text-om-faint">
                                        {state === 'valid' ? '建议保存 PDF 原件；本页只用于核对，不替代客户签收记录。' : '作废证书不可继续流转；请向签发方索取重签后的 COA。'}
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        {pdfUrl && (
                                            <a href={pdfUrl} className="btn-touch btn-primary text-xs">
                                                下载 PDF
                                            </a>
                                        )}
                                        <button type="button" onClick={() => window.print()} className="btn-touch btn-secondary text-xs">
                                            打印本页
                                        </button>
                                    </div>
                                </div>
                            )}
                        </>
                    )}

                    <footer className="mt-8 border-t border-om-line pt-3 text-[10px] leading-4 text-om-faint">
                        本页是逅唐茶业 OpenMES 的公开验真页，不进入后台导航；证书真伪以本页结论、签名记录与 PDF 页脚为准。
                    </footer>
                </main>
            </div>
        </>
    );
}

function Facts({ coa }) {
    const facts = [
        { label: '客户', value: coa.customer || '—', sub: coa.customer_code },
        { label: '产品', value: coa.product || '—', sub: coa.product_code },
        { label: '生产批次', value: coa.lot_number || coa.batch_number || '—', sub: coa.batch_number ? `批次号 ${coa.batch_number}` : null, mono: true },
        { label: '签发人', value: coa.issued_by || '—', sub: 'COA 签发' },
    ];

    return (
        <section className="print-avoid-break mt-4 grid gap-px overflow-hidden rounded-om-sm border border-om-line bg-om-line2 sm:grid-cols-2 lg:grid-cols-4">
            {facts.map((fact) => (
                <div key={fact.label} className="min-w-0 bg-om-card px-4 py-3">
                    <div className="text-[10px] font-bold tracking-[0.08em] text-om-faint">{fact.label}</div>
                    <div className={`mt-1 break-words text-[13px] font-bold leading-5 text-om-ink ${fact.mono ? 'font-mono' : ''}`}>{fact.value}</div>
                    {fact.sub && <div className="mt-1 truncate font-mono text-[10px] text-om-faint" title={fact.sub}>{fact.sub}</div>}
                </div>
            ))}
        </section>
    );
}

function SignatureCard({ signature }) {
    return (
        <div className="min-w-0 bg-om-card px-4 py-3">
            <div className="text-[10px] font-bold tracking-[0.08em] text-om-faint">{signature.meaning}</div>
            <div className="mt-1 truncate text-[15px] font-bold text-om-ink">{signature.name || '—'}</div>
            <div className="mt-0.5 text-[11px] text-om-muted">{signature.role || '质量人员'}</div>
            <div className="mt-2 flex items-center justify-between gap-2 text-[10px]">
                <span className="text-om-faint">{signature.signed_at ? formatDateTime(signature.signed_at) : '—'}</span>
                <span className={`inline-flex shrink-0 items-center gap-1 font-bold ${signature.verified ? 'text-om-running' : 'text-om-blocked'}`}>
                    <Icon name={signature.verified ? 'check' : 'x'} size={11} />
                    {signature.verified ? '校验通过' : '校验异常'}
                </span>
            </div>
            <div className="mt-1 font-mono text-[10px] text-om-faint">哈希 {signature.hash || '—'}</div>
        </div>
    );
}

function HashRow({ label, value }) {
    return (
        <div className="flex items-baseline justify-between gap-3 border-b border-om-line2 pb-1.5 last:border-0 last:pb-0">
            <dt className="text-[10px] font-bold text-om-faint">{label}</dt>
            <dd className="break-all font-mono text-[11px] text-om-muted">{value}</dd>
        </div>
    );
}
