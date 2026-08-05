<?php

namespace Modules\Pantheon\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for Datalab's PAWS REST API (https://paws.datalab.eu).
 *
 * Only the four operations this connector needs:
 *
 *   Users/authwithtoken     — session token for a username + company database
 *   DBObjects/selecttables  — generic paged SELECT over any Pantheon table/view,
 *                             which is how the views from the customer's spec
 *                             (vHF_WOExSetsItem, tHE_SetItem, vHE_SerialNoFree,
 *                             tHF_SetPrst) are read without direct MSSQL access
 *   Stock                   — stock levels
 *   Move/*                  — warehouse movement documents, used to book our
 *                             posted releases and receipts back into Pantheon
 *
 * Deliberately dumb: it speaks HTTP and returns arrays. Mapping Pantheon columns
 * onto OpenMES fields belongs to the Sync classes, so a customer whose views are
 * customised only needs their mapping changed, not this file.
 */
class PawsClient
{
    /**
     * Hard ceiling on pages per read. A PAWS build that ignores start/length would
     * return a full page forever, and a nightly sync that never ends is worse than
     * one that stops with a message.
     */
    private const MAX_PAGES = 10_000;

    private ?string $token = null;

    public function __construct(private PantheonSettings $settings) {}

    /**
     * Read a table or view, page by page, yielding rows as they arrive.
     *
     * A generator rather than a list: a full item table can be tens of thousands
     * of rows and the caller batches them into import chunks anyway.
     *
     * @param  list<string>  $fields  columns to return; empty = all
     * @param  array<string, mixed>  $conditions  column => value (equality)
     * @return \Generator<int, array<string, mixed>>
     */
    public function select(string $table, array $fields = [], array $conditions = [], ?string $sort = null): \Generator
    {
        $start = 0;
        $length = $this->settings->pageSize();
        $page = 0;
        $previousFingerprint = null;

        do {
            if (++$page > self::MAX_PAGES) {
                throw new RuntimeException(
                    'PAWS returned more than '.self::MAX_PAGES." pages for {$table}; aborting to avoid an endless read."
                );
            }

            $payload = [
                'masterTable' => ['tableName' => $table],
                'start' => $start,
                'length' => $length,
            ];

            if ($fields !== []) {
                $payload['fieldsToReturn'] = implode(',', $fields);
            }

            if ($conditions !== []) {
                $payload['customConditions'] = $this->conditions($conditions);
            }

            if ($sort !== null) {
                $payload['sortColumn'] = $sort;
                $payload['sortOrder'] = 'asc';
            }

            $rows = $this->post('/api/DBObjects/selecttables', $payload);
            $rows = $this->rows($rows);

            // Identical consecutive pages mean the offset is being ignored; stop
            // rather than yielding the same rows until the end of time.
            $fingerprint = md5(json_encode($rows));

            if ($rows !== [] && $fingerprint === $previousFingerprint) {
                throw new RuntimeException(
                    "PAWS returned the same page twice for {$table}; it appears to ignore paging."
                );
            }

            $previousFingerprint = $fingerprint;

            foreach ($rows as $row) {
                yield $row;
            }

            $start += $length;
        } while (count($rows) === $length);
    }

    /**
     * Stock levels, optionally narrowed to one classification.
     *
     * @return array<int, array<string, mixed>>
     */
    public function stock(?string $classification = null, ?string $date = null): array
    {
        return $this->rows($this->post('/api/Stock', array_filter([
            'primClassif' => $classification,
            'dateOfStock' => $date,
        ], fn ($v) => $v !== null)));
    }

    /** Document types configured in this Pantheon installation. */
    public function documentTypes(): array
    {
        return $this->rows($this->post('/api/Move/getdoctypes', []));
    }

    /**
     * Insert a movement document. Returns the Pantheon document key (acKey),
     * which is what we store on our side as the ERP reference.
     */
    public function insertMove(array $document): string
    {
        $response = $this->post('/api/Move/insert', $document);

        $key = $response['acKey'] ?? $response['data']['acKey'] ?? null;

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('PAWS Move/insert did not return a document key.');
        }

        return $key;
    }

    /** Move a document to another status (e.g. post it). */
    public function changeDocumentStatus(string $acKey, string $status): void
    {
        $this->post("/api/Move/changedocstatus/{$acKey}/{$status}", []);
    }

    // ── plumbing ────────────────────────────────────────────────────────────

    /**
     * Authenticate once per client instance. PAWS also exposes validatesession;
     * we re-authenticate on a 401 instead, which is simpler and self-healing.
     */
    private function authenticate(): void
    {
        $response = Http::acceptJson()
            ->timeout(30)
            ->post($this->settings->baseUrl().'/api/Users/authwithtoken', [
                'username' => $this->settings->username(),
                'password' => $this->settings->password(),
                'companyDB' => $this->settings->companyDb(),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('PAWS authentication failed with HTTP '.$response->status());
        }

        $token = $response->json('token') ?? $response->json('data.token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('PAWS authentication returned no token.');
        }

        $this->token = $token;
    }

    private function request(): PendingRequest
    {
        if ($this->token === null) {
            $this->authenticate();
        }

        return Http::acceptJson()
            ->withToken($this->token)
            ->timeout(60)
            ->baseUrl($this->settings->baseUrl());
    }

    /** @return array<string, mixed> */
    private function post(string $path, array $payload): array
    {
        $response = $this->request()->post($path, $payload);

        // A session can expire mid-sync; re-authenticate once and retry.
        if ($response->status() === 401) {
            $this->token = null;
            $response = $this->request()->post($path, $payload);
        }

        if (! $response->successful()) {
            throw new RuntimeException("PAWS {$path} failed with HTTP ".$response->status());
        }

        return (array) $response->json();
    }

    /**
     * PAWS wraps result sets differently per endpoint (`data`, `rows`, or a bare
     * array), so normalise once here rather than in every Sync class.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $response): array
    {
        foreach (['data', 'rows', 'result', 'items'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return array_values($response[$key]);
            }
        }

        return array_is_list($response) ? $response : [];
    }

    /**
     * PAWS expresses filters through a CustomConditions object. Kept in one place
     * because this is the shape most likely to need adjusting against a real
     * instance — the published swagger does not document the operator vocabulary.
     *
     * @param  array<string, mixed>  $conditions
     */
    private function conditions(array $conditions): array
    {
        return [
            'conditions' => array_map(
                fn ($column, $value) => ['column' => $column, 'operator' => '=', 'value' => $value],
                array_keys($conditions),
                array_values($conditions),
            ),
        ];
    }
}
