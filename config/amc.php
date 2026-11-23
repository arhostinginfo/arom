<?php

/*
| AMC (Annual Maintenance Contract) module (21/11/2026). Kept apart from CRM and Sales: its own tables (amc_*), pages,
| permissions and menu. App\Providers\AmcServiceProvider adds 'pages' and 'nav' below to config('nursery.pages') and
| config('nursery.nav') when the app starts, so Access rights and the menu bar list AMC with no change to their code.
*/

return [

    // Permission group "AMC" on Admin › Access rights, in the order of the AMC menu. Actions as in config('nursery.actions').
    'pages' => [
        'amc-dashboard' => ['label' => 'AMC dashboard', 'actions' => ['view']],
        'amc-client' => ['label' => 'AMC Client Inventory', 'actions' => ['view', 'add', 'edit']],
        // Import has its own row so the four columns of Access rights stay the same for every module.
        'amc-client-import' => ['label' => 'AMC Client Inventory · Import', 'actions' => ['add'], 'note' => 'Add = may import clients from a CSV file'],
        'amc-contract' => ['label' => 'AMC Tracker and AMC Report', 'actions' => ['view', 'add', 'edit', 'delete'], 'note' => 'Delete = delete a contract or one of its files'],
        'amc-return' => ['label' => 'Return & Replace', 'actions' => ['view', 'add', 'edit', 'delete']],
        'amc-vertical' => ['label' => 'Business segment (Vertical) master', 'actions' => ['view', 'add', 'edit', 'delete']],
        'amc-planter' => ['label' => 'Planter master', 'actions' => ['view', 'add', 'edit', 'delete']],
        'amc-plant' => ['label' => 'Plant master', 'actions' => ['view', 'add', 'edit', 'delete']],
        'amc-type' => ['label' => 'AMC type master', 'actions' => ['view', 'add', 'edit', 'delete']],
        'amc-employee' => ['label' => 'AMC Employee master', 'actions' => ['view', 'add', 'edit', 'delete']],
    ],

    // Top menu "AMC", placed before Admin. Each page is hidden from users without its View right.
    // (22/11/2026) The AMC list is the "AMC Report" of the AMC reports (below), no longer a menu page.
    'nav' => ['group' => 'Modules', 'key' => 'amc', 'icon' => 'sun', 'title' => 'AMC', 'reports' => 'AMC', 'subs' => [
        ['title' => 'Dashboard', 'perm' => 'amc-dashboard.view', 'route' => 'amc.dashboard', 'match' => 'amc.dashboard'],
        ['title' => 'AMC Client Inventory', 'perm' => 'amc-client.view', 'route' => 'amc.clients.index', 'match' => 'amc.clients.*'],
        ['title' => 'AMC Tracker', 'perm' => 'amc-contract.view', 'route' => 'amc.contract.index', 'match' => 'amc.contract.*'],
        ['title' => 'Return & Replace', 'perm' => 'amc-return.view', 'route' => 'amc.returns.index', 'match' => 'amc.returns.*'],
        // (22/11/2026) The five masters are one menu item: the Masters page lists them, and tabs on each master move between them.
        ['title' => 'Masters', 'perm' => 'amc-masters.view', 'route' => 'amc.masters.home', 'match' => ['amc.masters.*', 'amc.employees.*']],
    ]],

    /*
    | Simple AMC masters on one shared screen (list with the add / edit form beside it), like the Sales masters.
    | perm: the page of its rights. used: table.column that point at a row; a row in use cannot be deleted.
    */
    'masters' => [
        'verticals' => ['title' => 'Business segment master', 'item' => 'business segment', 'perm' => 'amc-vertical', 'model' => \App\Models\Amc\AmcVertical::class,
            'used' => ['amc_clients.amc_vertical_id'], 'hint' => 'The vertical of an AMC client, e.g. "AMC" or "AMC + Rental".',
            'fields' => ['name' => ['label' => 'Name', 'max' => 100]]],
        'planters' => ['title' => 'Planter master', 'item' => 'planter', 'perm' => 'amc-planter', 'model' => \App\Models\Amc\AmcPlanter::class,
            'used' => ['amc_client_lines.amc_planter_id', 'amc_return_items.amc_planter_id'], 'hint' => 'Planters placed at a client\'s locations.',
            'fields' => ['name' => ['label' => 'Name', 'max' => 100]]],
        'plants' => ['title' => 'Plant master', 'item' => 'plant', 'perm' => 'amc-plant', 'model' => \App\Models\Amc\AmcPlant::class,
            'used' => ['amc_client_lines.amc_plant_id', 'amc_returns.amc_plant_id', 'amc_return_items.amc_plant_id'], 'hint' => 'Size is free text, e.g. "5 ft". Return & Replace takes the size and the rate from here.',
            // (23/11/2026) rate: the price of one plant, taken by the Return & Replace rows
            'fields' => ['name' => ['label' => 'Name', 'max' => 100], 'size' => ['label' => 'Size', 'max' => 50, 'optional' => true],
                'rate' => ['label' => 'Rate (₹)', 'max' => 20, 'optional' => true, 'money' => true]]],
        'types' => ['title' => 'AMC type master', 'item' => 'AMC type', 'perm' => 'amc-type', 'model' => \App\Models\Amc\AmcType::class,
            'used' => ['amc_contracts.amc_type_id', 'amc_returns.amc_type_id'], 'hint' => 'The kinds of maintenance contract.',
            'fields' => ['name' => ['label' => 'Name', 'max' => 100]]],
    ],

    // (22/11/2026) AMC reports, added to config('nursery.reports', 'report_routes', 'report_perms') by AmcServiceProvider:
    // they show under the AMC menu and on Reports, like the CRM and Sales reports.
    'reports' => ['AMC Report'],
    'report_routes' => ['reports.amc.report'],
    'report_perm' => 'amc-contract.view',

    // Fixed lists (no table).
    'billing_cycles' => ['Monthly', 'Quarterly', 'Half-yearly', 'Yearly'],
    'employee_roles' => ['Operation Manager', 'Gardener', 'Other'],
    'qualities' => ['Reusable', 'Needs Treatment', 'Repairable', 'Dead'],

    /*
    | Files of a contract (amc_files.category). types: what the box takes. Several images chosen together in one box are
    | combined into one collage JPEG (App\Services\Amc\AmcImages); PDFs are kept as they are.
    */
    'file_categories' => [
        'po' => ['label' => 'Customer PO / Agreement', 'types' => ['pdf', 'jpg', 'jpeg', 'png']],
        'challan_doc' => ['label' => 'Delivery challan · documents', 'types' => ['pdf']],
        'challan_image' => ['label' => 'Delivery challan · images', 'types' => ['jpg', 'jpeg', 'png']],
        'deployment' => ['label' => 'Deployment images', 'types' => ['jpg', 'jpeg', 'png']],
    ],

    // Deployment images older than this many days are deleted by "php artisan amc:purge-old-images" (daily).
    'deployment_keep_days' => 365,

    // Private disk for contract files and employee photos: DOCUMENTS_DISK unless AMC_FILES_DISK says otherwise.
    'files_disk' => env('AMC_FILES_DISK', env('DOCUMENTS_DISK', 'local')),

    // Uploads: per file, files per upload, and the image sizes (pixels, JPEG quality) used when GD is available.
    'max_file_kb' => 10240,
    'max_files' => 20,
    // (21/11/2026) All the files of one upload together: 40 MB, the server's post limit, so an upload over it gets a clear
    // message instead of the server's generic error.
    'max_total_kb' => 40960,
    'collage' => ['columns' => 2, 'tile_width' => 800, 'quality' => 75],
    'single_max' => 1600,
    'photo_max' => 600,

    // Contract colours on the AMC list: Red when the end date is this many days away or less; renewal banner within renew_days.
    'due_days' => 30,
    'renew_days' => 60,
];
