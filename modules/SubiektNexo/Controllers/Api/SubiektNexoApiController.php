<?php

namespace Modules\SubiektNexo\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SubiektNexo\Services\ProductSyncService;
use Modules\SubiektNexo\Services\SferaClient;

class SubiektNexoApiController extends Controller
{
    public function __construct(
        private SferaClient $client,
        private ProductSyncService $syncService,
    ) {}

    /**
     * Check Sfera API health + connection status.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'data' => [
                'health' => $this->client->health(),
                'connection' => $this->client->status(),
            ],
        ]);
    }

    /**
     * Force connect to Subiekt.
     */
    public function connect(): JsonResponse
    {
        $result = $this->client->connect();

        return response()->json([
            'message' => $result['connected'] ? 'Connected to Subiekt' : 'Connection failed',
            'data' => $result,
        ]);
    }

    /**
     * List products from Subiekt (raw, no sync).
     */
    public function products(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 50);

        return response()->json([
            'data' => $this->client->getProducts($limit),
        ]);
    }

    /**
     * Get single product from Subiekt by symbol.
     */
    public function product(string $symbol): JsonResponse
    {
        $item = $this->client->getProduct($symbol);

        if (! $item) {
            return response()->json(['message' => 'Product not found in Subiekt'], 404);
        }

        return response()->json(['data' => $item]);
    }

    /**
     * Sync products from Subiekt to OpenMES materials.
     */
    public function sync(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 500);
        $result = $this->syncService->syncProducts($limit);

        return response()->json([
            'message' => "Sync complete: {$result['created']} created, {$result['updated']} updated",
            'data' => $result,
        ]);
    }

    /**
     * Sync single product by symbol.
     */
    public function syncProduct(string $symbol): JsonResponse
    {
        $material = $this->syncService->syncProduct($symbol);

        if (! $material) {
            return response()->json(['message' => 'Product not found in Subiekt'], 404);
        }

        return response()->json([
            'message' => 'Product synced',
            'data' => $material->load('materialType'),
        ]);
    }

    /**
     * List contractors from Subiekt.
     */
    public function contractors(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 50);

        return response()->json([
            'data' => $this->client->getContractors($limit),
        ]);
    }
}
