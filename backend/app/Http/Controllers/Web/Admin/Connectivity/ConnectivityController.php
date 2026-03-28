<?php

namespace App\Http\Controllers\Web\Admin\Connectivity;

use App\Http\Controllers\Controller;
use App\Models\MachineConnection;

class ConnectivityController extends Controller
{
    public function index()
    {
        $connections = MachineConnection::withCount('topics')
            ->with('mqttConnection')
            ->orderBy('name')
            ->get();

        return view('admin.connectivity.index', compact('connections'));
    }
}
