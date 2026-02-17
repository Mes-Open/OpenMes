<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CsvImportController extends Controller
{
    public function index()
    {
        return view('admin.csv-import');
    }

    // Note: For this migration, we'll create a simple UI placeholder.
    // The full CSV import functionality can be implemented later or
    // can continue using the API endpoints from the frontend
}
