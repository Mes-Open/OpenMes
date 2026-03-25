<?php

namespace App\Services\Edge;

use App\Models\MachineEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EdgeNodeSyncService
{
    /**
     * Process offline buffer and sync to Cloud backend.
     */
    public function sync(string $cloudUrl, string $apiKey): array
    {
        $unsyncedEvents = MachineEvent::where('synced_to_cloud', false)
            ->limit(100)
            ->get();

        if ($unsyncedEvents->isEmpty()) {
            return ['synced' => 0];
        }

        try {
            $response = Http::withToken($apiKey)
                ->post($cloudUrl . '/api/v1/industrial/sync-telemetry', [
                    'events' => $unsyncedEvents->toArray(),
                    'edge_node_id' => config('openmmes.edge_id'),
                    'batch_timestamp' => now()->toIso8601String(),
                ]);

            if ($response->successful()) {
                MachineEvent::whereIn('id', $unsyncedEvents->pluck('id'))
                    ->update(['synced_to_cloud' => true]);

                return ['synced' => $unsyncedEvents->count()];
            }

            Log::error("Cloud sync failed: {$response->status()}");
            return ['synced' => 0, 'error' => $response->status()];

        } catch (\Exception $e) {
            Log::error("Edge sync error: {$e->getMessage()}");
            return ['synced' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Local storage buffering when cloud is offline.
     */
    public function bufferEvent(array $eventData): void
    {
        // Handled by the record() method in MachineEventStore (persistent local SQL)
    }
}
