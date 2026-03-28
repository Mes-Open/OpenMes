<?php

namespace App\Http\Controllers\Web\Admin\Connectivity;

use App\Http\Controllers\Controller;
use App\Models\MachineConnection;
use App\Models\MachineTopic;
use App\Models\TopicMapping;
use Illuminate\Http\Request;

class TopicMappingController extends Controller
{
    public function store(Request $request, MachineConnection $mqttConnection, MachineTopic $topic)
    {
        abort_if($topic->machine_connection_id !== $mqttConnection->id, 403);

        $validated = $this->validateMapping($request);

        $topic->mappings()->create($validated + [
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Mapping added.');
    }

    public function update(Request $request, MachineConnection $mqttConnection, MachineTopic $topic, TopicMapping $mapping)
    {
        abort_if($topic->machine_connection_id !== $mqttConnection->id, 403);
        abort_if($mapping->machine_topic_id !== $topic->id, 403);

        $validated = $this->validateMapping($request);
        $mapping->update($validated + [
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Mapping updated.');
    }

    public function destroy(MachineConnection $mqttConnection, MachineTopic $topic, TopicMapping $mapping)
    {
        abort_if($topic->machine_connection_id !== $mqttConnection->id, 403);
        abort_if($mapping->machine_topic_id !== $topic->id, 403);

        $mapping->delete();
        return back()->with('success', 'Mapping deleted.');
    }

    private function validateMapping(Request $request): array
    {
        $validated = $request->validate([
            'description'   => ['nullable', 'string', 'max:255'],
            'field_path'    => ['nullable', 'string', 'max:255'],
            'action_type'   => ['required', 'in:update_batch_step,update_work_order_qty,create_issue,update_line_status,set_work_order_status,log_event,webhook_forward'],
            'action_params' => ['nullable', 'string'],  // JSON string from textarea
            'condition_expr' => ['nullable', 'string', 'max:255'],
            'priority'      => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        // Parse action_params JSON
        $paramsRaw = $validated['action_params'] ?? null;
        if ($paramsRaw) {
            $params = json_decode($paramsRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                back()->withErrors(['action_params' => 'Invalid JSON in action parameters.']);
            }
            $validated['action_params'] = $params;
        } else {
            $validated['action_params'] = null;
        }

        return $validated;
    }
}
