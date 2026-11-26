<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CoaDocument;
use App\Models\CoaVerificationToken;
use App\Models\ElectronicSignature;
use App\Services\Quality\CoaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Public, read-only COA verification. The URL carries a random bearer token;
 * the page never exposes internal IDs, and void/revoked records do not expose
 * old signatures.
 */
class CoaVerificationController extends Controller
{
    public function __construct(private readonly CoaService $coa)
    {
    }

    public function show(Request $request, string $token)
    {
        $record = CoaVerificationToken::findByRawToken($token);

        if (! $record) {
            return $this->notFound($request);
        }

        $document = $record->document()
            ->with([
                'batch',
                'customer',
                'productType',
                'inspector',
                'approvedBy',
                'issuedBy',
                'voidedBy',
                'versions',
                'signatures.user',
            ])
            ->first();

        if (! $document) {
            return $this->notFound($request);
        }

        $coaSignatures = $document->signatures
            ->filter(fn (ElectronicSignature $signature) => in_array($signature->meaning, [
                ElectronicSignature::MEANING_COA_INSPECTION,
                ElectronicSignature::MEANING_COA_APPROVAL,
                ElectronicSignature::MEANING_COA_ISSUE,
            ], true))
            ->values();

        $verified = $coaSignatures->isNotEmpty()
            && $coaSignatures->every(fn (ElectronicSignature $signature) => $this->coa->verifySignature($signature));

        if ($document->status === CoaDocument::STATUS_VOID) {
            $state = 'void';
        } elseif (! $record->isActive()) {
            $state = 'revoked';
        } elseif (! $document->isIssued()) {
            $state = 'unavailable';
        } elseif (! $verified) {
            $state = 'invalid';
        } else {
            $state = 'valid';
        }

        $showSignatures = in_array($state, ['valid', 'invalid'], true);
        $latestVersion = (int) $document->versions->max('version');
        $reissueVersion = $latestVersion > (int) $document->current_version
            ? 'V'.str_pad((string) $latestVersion, 2, '0', STR_PAD_LEFT)
            : null;

        $payload = [
            'state' => $state,
            'coa' => [
                'number' => $document->coa_number,
                'version' => 'V'.str_pad((string) $document->current_version, 2, '0', STR_PAD_LEFT),
                'issued_at' => $document->issued_at?->toIso8601String(),
                'customer' => $document->customer_name ?? $document->customer?->name,
                'customer_code' => $document->customer_code,
                'product' => $document->product_name ?? $document->productType?->name,
                'product_code' => $document->product_code,
                'batch_number' => $document->batch?->batch_number,
                'lot_number' => $document->lot_number ?? $document->batch?->lot_number,
                'issued_by' => $document->issuedBy?->name,
                'voided_at' => $document->voided_at?->toIso8601String(),
                'void_reason' => $document->void_reason,
                'reissue_version' => $reissueVersion,
            ],
            'token' => [
                'issued_at' => $record->issued_at?->toIso8601String(),
                'revoked_at' => $record->revoked_at?->toIso8601String(),
                'revoke_reason' => $record->revoke_reason,
                'short_hash' => $record->shortHash(),
            ],
            'signatures' => $showSignatures
                ? $coaSignatures
                    ->sortBy(fn (ElectronicSignature $signature) => array_search($signature->meaning, [
                        ElectronicSignature::MEANING_COA_INSPECTION,
                        ElectronicSignature::MEANING_COA_APPROVAL,
                        ElectronicSignature::MEANING_COA_ISSUE,
                    ], true))
                    ->map(fn (ElectronicSignature $signature) => [
                        'name' => $signature->user?->name,
                        'role' => $this->roleLabel($signature),
                        'meaning' => $this->meaningLabel($signature->meaning),
                        'signed_at' => $signature->signed_at?->toIso8601String(),
                        'hash' => substr((string) $signature->record_hash, 0, 12),
                        'verified' => $this->coa->verifySignature($signature),
                    ])
                    ->values()
                    ->all()
                : [],
            'proof_hash' => substr((string) $coaSignatures
                ->firstWhere('meaning', ElectronicSignature::MEANING_COA_ISSUE)
                ?->record_hash, 0, 16),
            'pdf_url' => $state === 'valid' ? route('coa.verify.pdf', ['token' => $token]) : null,
        ];

        return Inertia::render('verify/Coa', $payload)
            ->toResponse($request)
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function pdf(Request $request, string $token)
    {
        $record = CoaVerificationToken::findByRawToken($token);

        if (! $record) {
            abort(404);
        }

        if (! $record->isActive()) {
            abort(410, '此验真链接已失效，请向签发方索取最新链接。');
        }

        $document = $record->document()->first();
        if (! $document || ! $document->isIssued() || ! $document->batch) {
            abort(404);
        }

        return app(\App\Http\Controllers\Web\Admin\CoaController::class)->pdf($document->batch);
    }

    private function notFound(Request $request)
    {
        return Inertia::render('verify/Coa', ['state' => 'not_found'])
            ->toResponse($request)
            ->setStatusCode(404)
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function meaningLabel(string $meaning): string
    {
        return match ($meaning) {
            ElectronicSignature::MEANING_COA_INSPECTION => '检验人签字',
            ElectronicSignature::MEANING_COA_APPROVAL => 'QA 批准',
            ElectronicSignature::MEANING_COA_ISSUE => 'COA 签发',
            default => '签名',
        };
    }

    private function roleLabel(ElectronicSignature $signature): string
    {
        $roles = $signature->metadata['signer_roles'] ?? $signature->user?->getRoleNames()->all() ?? [];

        return match ($roles[0] ?? null) {
            'Admin' => '管理员',
            'QA' => 'QA 负责人',
            'Supervisor' => '生产主管',
            'Operator' => '操作工',
            default => '质量人员',
        };
    }
}
