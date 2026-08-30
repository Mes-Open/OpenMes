<?php

namespace App\Http\Controllers\Web\Admin\Connectivity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\Connectivity\TopicMappingRequest;
use App\Models\MachineConnection;
use App\Models\MachineTopic;
use App\Models\TopicMapping;

class TopicMappingController extends Controller
{
    public function store(TopicMappingRequest $request, MachineConnection $mqttConnection, MachineTopic $topic)
    {
        abort_if($topic->machine_connection_id !== $mqttConnection->id, 403);

        $topic->mappings()->create($request->mappingData() + [
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Mapping added.');
    }

    public function update(TopicMappingRequest $request, MachineConnection $mqttConnection, MachineTopic $topic, TopicMapping $mapping)
    {
        abort_if($topic->machine_connection_id !== $mqttConnection->id, 403);
        abort_if($mapping->machine_topic_id !== $topic->id, 403);

        $mapping->update($request->mappingData() + [
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
}
