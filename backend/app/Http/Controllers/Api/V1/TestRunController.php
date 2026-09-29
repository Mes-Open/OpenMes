<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IngestTestRunRequest;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Traceability\TestLog\TestLogImporter;
use App\Services\Traceability\TestLog\TestLogParser;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * Test results straight from the tester. The station posts the run it just
 * finished - the same lines it writes to its JSONL log, or a plain JSON
 * object - and the importer records it on the unit's history exactly as the
 * file import would: the unit is registered on first sight, the verdict runs
 * the plant's fail policy, a pallet the unit is on re-reads its quality, and
 * a run sent twice is recorded once.
 */
class TestRunController extends Controller
{
    public function __construct(
        private readonly TestLogParser $parser,
        private readonly TestLogImporter $importer,
    ) {}

    public function store(IngestTestRunRequest $request): JsonResponse
    {
        $records = $request->records();
        if ($records === []) {
            return response()->json(['message' => __('The request holds no test-run records.')], 422);
        }

        try {
            $run = $this->parser->parseRecords($records);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => __('Test log not recognised: :reason', ['reason' => $e->getMessage()])], 422);
        }
        if ($run === null) {
            return response()->json(['message' => __('The records do not hold a complete test run.')], 422);
        }

        try {
            $result = $this->importer->import($run, array_filter([
                'work_order' => $request->filled('work_order') ? WorkOrder::where('order_no', $request->input('work_order'))->first() : null,
                'workstation' => $request->filled('workstation') ? Workstation::where('code', $request->input('workstation'))->first() : null,
                // The run is booked on the token that sent it; the name in the log
                // stays in the run's parameters, it does not pick the account.
                'operator' => $request->user(),
                'source' => $request->input('source', 'api'),
            ]));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $duplicate = $result['status'] === TestLogImporter::DUPLICATE;
        $unit = $result['unit'];

        return response()->json([
            'message' => $duplicate ? __('Test run already recorded') : __('Test run recorded'),
            'status' => $result['status'],
            'data' => [
                'unit' => ['id' => $unit->id, 'serial_no' => $unit->serial_no, 'psn' => $unit->psn, 'status' => $unit->status, 'work_order_id' => $unit->work_order_id],
                'run_id' => $run->runId,
                'verdict' => $run->verdict,
                'history_id' => $result['entry']?->id,
            ],
        ], $duplicate ? 200 : 201);
    }
}
