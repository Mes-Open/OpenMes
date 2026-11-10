<?php

namespace App\Http\Controllers\Web\Admin\Connectivity;

use App\Http\Controllers\Controller;
use App\Models\MachineConnection;
use App\Models\MachineTopic;
use Illuminate\Http\Request;

class MachineTopicController extends Controller
{
    public function store(Request $request, MachineConnection $mqttConnection)
    {
        $validated = $request->validate([
            'topic_pattern' => ['required', 'string', 'max:500'],
            'payload_format' => ['required', 'in:json,plain,csv,hex'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $mqttConnection->topics()->create([
            'topic_pattern' => $validated['topic_pattern'],
            'payload_format' => $validated['payload_format'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', __('Topic added.'));
    }

    public function update(Request $request, MachineConnection $mqttConnection, MachineTopic $topic)
    {
        $this->authorizeTopicBelongsToConnection($topic, $mqttConnection);

        $validated = $request->validate([
            'topic_pattern' => ['required', 'string', 'max:500'],
            'payload_format' => ['required', 'in:json,plain,csv,hex'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $topic->update([
            'topic_pattern' => $validated['topic_pattern'],
            'payload_format' => $validated['payload_format'],
            'description' => $validated['description'] ?? null,
            // The edit form does not send it: fixing a typo must not switch a
            // deliberately disabled topic back on.
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $topic->is_active,
        ]);

        return back()->with('success', __('Topic updated.'));
    }

    public function destroy(MachineConnection $mqttConnection, MachineTopic $topic)
    {
        $this->authorizeTopicBelongsToConnection($topic, $mqttConnection);
        $topic->delete();

        return back()->with('success', __('Topic deleted.'));
    }

    private function authorizeTopicBelongsToConnection(MachineTopic $topic, MachineConnection $connection): void
    {
        abort_if($topic->machine_connection_id !== $connection->id, 403);
    }
}
