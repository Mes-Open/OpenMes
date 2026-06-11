<?php

use App\Models\Area;
use App\Models\Batch;
use App\Models\Issue;
use App\Models\Line;
use App\Models\Material;
use App\Models\ProductType;
use App\Models\Shift;
use App\Models\Site;
use App\Models\Tool;
use App\Models\WorkOrder;
use App\Models\Worker;
use App\Models\Workstation;

return [

    /*
    |--------------------------------------------------------------------------
    | Custom-field entity registry
    |--------------------------------------------------------------------------
    |
    | The single source of truth for which entity-types may carry admin-defined
    | custom fields. Each key is the `entity_type` alias stored on
    | custom_field_definitions.entity_type — by convention the snake_case of the
    | model's class basename, which is also what HasCustomFields derives by
    | default (so models need no extra config).
    |
    |   model      — Eloquent model class
    |   table      — DB table; the add-custom-fields migration adds the
    |                `custom_fields` JSON column to every table listed here
    |   label      — human label for the definition-management UI dropdown
    |   collection — ShapeRegistry collection name to add `custom_fields` to in
    |                the sync phase (Phase 5). null = verify/fill against
    |                ShapeRegistry when that phase lands.
    |
    | "Broad + configurable": the column exists on all of these, but an
    | entity-type only surfaces fields once an admin defines them.
    |
    */

    'entities' => [
        'work_order'  => ['model' => WorkOrder::class,  'table' => 'work_orders',  'label' => 'Work Order',  'collection' => 'work_orders_active'],
        'material'    => ['model' => Material::class,    'table' => 'materials',    'label' => 'Material',     'collection' => 'materials'],
        'product_type'=> ['model' => ProductType::class, 'table' => 'product_types','label' => 'Product Type', 'collection' => 'product_types'],
        'batch'       => ['model' => Batch::class,       'table' => 'batches',      'label' => 'Batch',        'collection' => null],
        'issue'       => ['model' => Issue::class,       'table' => 'issues',       'label' => 'Issue',        'collection' => 'issues_open'],
        'site'        => ['model' => Site::class,        'table' => 'sites',        'label' => 'Site',         'collection' => 'sites'],
        'area'        => ['model' => Area::class,        'table' => 'areas',        'label' => 'Area',         'collection' => null],
        'line'        => ['model' => Line::class,        'table' => 'lines',        'label' => 'Line',         'collection' => 'lines_active'],
        'workstation' => ['model' => Workstation::class, 'table' => 'workstations', 'label' => 'Workstation',  'collection' => null],
        'worker'      => ['model' => Worker::class,      'table' => 'workers',      'label' => 'Worker',       'collection' => null],
        'shift'       => ['model' => Shift::class,       'table' => 'shifts',       'label' => 'Shift',        'collection' => null],
        'tool'        => ['model' => Tool::class,        'table' => 'tools',        'label' => 'Tool',         'collection' => null],
    ],

];
