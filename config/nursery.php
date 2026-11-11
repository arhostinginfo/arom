<?php

/*
| Pages, permissions and the left menu, taken from Nursery.xlsx (blue bar on every
| sheet and the ADMIN tab) and the decisions register (D2, D19, S8).
|
| Every page gets "<page>.<action>" permissions. Actions follow Access rights in
| decision D19: View, Add, Edit, Delete. Pages that only have settings use View and
| Edit; the activity log and reports are view only.
*/

return [

    'actions' => [
        'view' => 'View',
        'add' => 'Add',
        'edit' => 'Edit',
        'delete' => 'Delete',
    ],

    // Permission groups in the order of the left menu (decision S8).
    'pages' => [
        'Dashboard' => [
            'dashboard' => ['label' => 'Dashboard', 'actions' => ['view']],
        ],
        'CRM' => [
            // CRM can be switched off as a whole (01/11/2026): every CRM page belongs to the 'crm' part. A page may belong to
            // more than one part ('module' => [...]) and is then refused while any of them is off.
            'enquiry' => ['label' => 'Enquiry (lead) and follow-ups', 'actions' => ['view', 'add', 'edit', 'delete'], 'module' => 'crm'],
            'site-visit' => ['label' => 'Site visit', 'actions' => ['view', 'add', 'edit', 'delete'], 'module' => ['crm', 'site_visit']],
            // The label names BOQ and costing only while those parts are switched on (see 'modules').
            'quotation' => ['label' => 'Quotation', 'label_parts' => ['boq' => 'BOQ', 'costing' => 'costing'], 'actions' => ['view', 'add', 'edit', 'delete'], 'module' => 'crm'],
            // Edit = approve or reject quotations sent for internal approval.
            'quotation-approval' => ['label' => 'Quotation approval', 'actions' => ['view', 'edit'], 'module' => 'crm'],
            // Separate PO menu removed (02/11/2026): the PO / Agreement rights are CRM's again, behind the CRM switch.
            'customer-po' => ['label' => 'PO / Agreement received', 'actions' => ['view', 'add', 'edit', 'delete'], 'module' => 'crm'],
            'prospect' => ['label' => 'Prospect', 'actions' => ['view', 'add', 'edit', 'delete'], 'module' => 'crm'],
            // Contact / POC of a client (CRM changes Word file, built 14/10/2026).
            'contact' => ['label' => 'Contact / POC', 'actions' => ['view', 'add', 'edit', 'delete'], 'module' => 'crm'],
        ],
        'Sales' => [
            // Delete on a tax invoice = cancel it: numbers stay in one unbroken series (D16, C12).
            'tax-invoice' => ['label' => 'Tax invoice', 'actions' => ['view', 'add', 'edit', 'delete']],
            'sales-order' => ['label' => 'Sales order', 'actions' => ['view', 'add', 'edit', 'delete']],
            // Edit = approve or reject sales orders sent for approval (D3).
            'sales-order-approval' => ['label' => 'Sales order approval', 'actions' => ['view', 'edit']],
        ],
        'Purchase' => [
            'grn' => ['label' => 'GRN entry', 'actions' => ['view', 'add', 'edit', 'delete']],
            // Edit = give the quality decision (Approved / Rejected) on a matched GRN, which brings its stock in (27/10/2026).
            'grn-quality' => ['label' => 'GRN quality approval', 'actions' => ['view', 'edit']],
            'purchase-order' => ['label' => 'Purchase order', 'actions' => ['view', 'add', 'edit', 'delete']],
            // Edit = approve or reject purchase orders sent for approval (D3, 27/10/2026).
            'purchase-order-approval' => ['label' => 'Purchase order approval', 'actions' => ['view', 'edit']],
            'stock-transfer' => ['label' => 'Stock transfer', 'actions' => ['view', 'add', 'edit', 'delete']],
            // Edit = approve or reject stock transfers sent for approval, and cancel an approved one (27/10/2026).
            'stock-transfer-approval' => ['label' => 'Stock transfer approval', 'actions' => ['view', 'edit']],
        ],
        'Accounts' => [
            'party-ledger' => ['label' => 'Party ledger', 'actions' => ['view']],
            'accounts-entry' => ['label' => 'Receivables / Payables / Expenses / Bank entry', 'actions' => ['view', 'add', 'edit', 'delete']],
        ],
        'Admin' => [
            'users' => ['label' => 'Users list', 'actions' => ['view', 'add', 'edit', 'delete']],
            'access-rights' => ['label' => 'Access rights (roles)', 'actions' => ['view', 'add', 'edit', 'delete']],
            'approval' => ['label' => 'Approval', 'actions' => ['view', 'edit']],
            'dashboard-config' => ['label' => 'Dashboard', 'actions' => ['view', 'edit']],
            'config' => ['label' => 'Config', 'actions' => ['view', 'edit']],
            'page-setup' => ['label' => 'Page setup', 'actions' => ['view', 'edit']],
            'masters' => ['label' => 'Masters', 'actions' => ['view', 'add', 'edit', 'delete']],
            'activity-log' => ['label' => 'Activity log', 'actions' => ['view']],
        ],
        'Reports' => [
            'reports-crm' => ['label' => 'CRM reports', 'actions' => ['view'], 'module' => 'crm'],
            'reports-sales' => ['label' => 'Sales reports', 'actions' => ['view']],
            'reports-purchase' => ['label' => 'Purchase reports', 'actions' => ['view']],
            'reports-accounts' => ['label' => 'Accounts reports', 'actions' => ['view']],
        ],
    ],

    // Default roles (decision D2). Admin always has every permission.
    'roles' => [
        'Admin' => '*',
        'Sales' => [
            'dashboard.view',
            'enquiry.*', 'site-visit.*', 'quotation.*', 'customer-po.*', 'prospect.*', 'contact.*', 'tax-invoice.*', 'sales-order.*',
            'reports-crm.view', 'reports-sales.view',
        ],
        'Purchase' => [
            'dashboard.view',
            'grn.*', 'purchase-order.*', 'stock-transfer.*',
            'reports-purchase.view',
        ],
        'Accounts' => [
            'dashboard.view',
            'party-ledger.view', 'accounts-entry.*',
            'reports-accounts.view',
        ],
    ],

    // Left menu. "route" pages are built; "preview" pages show the prototype's sample screen.
    'nav' => [
        ['group' => 'Overview', 'key' => 'dashboard', 'icon' => 'dash', 'title' => 'Dashboard', 'perm' => 'dashboard.view', 'route' => 'dashboard', 'match' => 'dashboard'],
        // In the order of the CRM workflow: lead, site visit, quotation, PO.
        ['group' => 'Modules', 'key' => 'crm', 'icon' => 'crm', 'title' => 'CRM', 'reports' => 'CRM', 'subs' => [
            // The six steps of an enquiry, in the order of its progress bar (Sat 18/10/2026), then the other CRM pages.
            ['title' => '1 Enquiry / Lead', 'perm' => 'enquiry.view', 'route' => 'crm.enquiries.index', 'match' => 'crm.enquiries.*'],
            ['title' => '2 Site visit', 'perm' => 'site-visit.view', 'route' => 'crm.site-visits.index', 'match' => 'crm.site-visits.*', 'module' => 'site_visit'],
            ['title' => '3 Quotation', 'title_from' => 'quotation_step', 'perm' => 'quotation.view', 'route' => 'crm.quotations.index', 'match' => 'crm.quotations.*'],
            ['title' => '4 Approval', 'perm' => 'quotation-approval.view', 'route' => 'crm.quotations.list', 'params' => ['view' => 'approval'], 'match' => 'crm.quotations.list'],
            ['title' => '5 Quotation sent', 'perm' => 'quotation.view', 'route' => 'crm.quotations.list', 'params' => ['view' => 'sent'], 'match' => 'crm.quotations.list'],
            ['title' => '6 PO / Agreement', 'perm' => 'customer-po.view', 'route' => 'crm.customer-pos.index', 'match' => 'crm.customer-pos.*'],
            ['title' => 'Prospect', 'perm' => 'prospect.view', 'route' => 'crm.prospects.index', 'match' => 'crm.prospects.*'],
            ['title' => 'Contacts', 'perm' => 'contact.view', 'route' => 'crm.contacts.index', 'match' => 'crm.contacts.*'],
        ]],
        ['group' => 'Modules', 'key' => 'sales', 'icon' => 'receipt', 'title' => 'Sales', 'reports' => 'Sales', 'subs' => [
            // Sales order and tax invoice are one page (SALES A24); each item opens it at its own card.
            ['title' => 'Tax Invoice', 'perm' => 'tax-invoice.view', 'route' => 'sales.invoices.index', 'match' => 'sales.invoices.*'],
            ['title' => 'Sales Order', 'perm' => 'sales-order.view', 'route' => 'sales.orders.index', 'match' => 'sales.orders.*'],
        ]],
        ['group' => 'Modules', 'key' => 'purchase', 'icon' => 'cart', 'title' => 'Purchase', 'reports' => 'Purchase', 'subs' => [
            // Built 27/10/2026; "preview" keeps /app/grn (the old sample screen's address) behind the same right.
            ['title' => 'GRN Entry', 'perm' => 'grn.view', 'route' => 'purchase.grns.index', 'match' => 'purchase.grns.*', 'preview' => 'grn'],
            // Built 27/10/2026; "preview" keeps /app/po (the old sample screen's address) behind the same right.
            ['title' => 'Purchase Order', 'perm' => 'purchase-order.view', 'route' => 'purchase.orders.index', 'match' => 'purchase.orders.*', 'preview' => 'po',
                'preview_perm' => 'purchase-order.view|purchase-order-approval.view'],
            ['title' => 'Stock Transfer', 'perm' => 'stock-transfer.view', 'route' => 'purchase.stock-transfers.index', 'match' => 'purchase.stock-transfers.*'],
        ]],
        ['group' => 'Modules', 'key' => 'accounts', 'icon' => 'bank', 'title' => 'Accounts', 'reports' => 'Accounts', 'subs' => [
            ['title' => 'Party Ledger', 'perm' => 'party-ledger.view', 'preview' => 'ledger'],
            ['title' => 'Receivables / Payables / Expenses / Bank entry', 'perm' => 'accounts-entry.view', 'preview' => 'entry'],
        ]],
        ['group' => 'Settings', 'key' => 'admin', 'icon' => 'cog', 'title' => 'Admin', 'subs' => [
            ['title' => 'Users list', 'perm' => 'users.view', 'route' => 'admin.users.index', 'match' => 'admin.users.*'],
            ['title' => 'Access rights', 'perm' => 'access-rights.view', 'route' => 'admin.roles.index', 'match' => 'admin.roles.*'],
            ['title' => 'Approval', 'perm' => 'approval.view', 'route' => 'admin.approval.edit', 'match' => 'admin.approval.*'],
            ['title' => 'Dashboard', 'perm' => 'dashboard-config.view', 'preview' => 'dashcfg'],
            ['title' => 'Config', 'perm' => 'config.view', 'route' => 'admin.config.edit', 'match' => 'admin.config.*'],
            ['title' => 'Page setup', 'perm' => 'page-setup.view', 'route' => 'admin.page-setup.index', 'match' => 'admin.page-setup.*'],
            // Masters not built yet still open their sample screens (page "master") from the Masters page.
            ['title' => 'Masters', 'perm' => 'masters.view', 'route' => 'admin.masters.index', 'match' => 'admin.masters.*', 'preview' => 'master'],
            ['title' => 'Activity log', 'perm' => 'activity-log.view', 'route' => 'admin.activity.index', 'match' => 'admin.activity.*'],
        ]],
        ['group' => '', 'key' => 'reports', 'icon' => 'chart', 'title' => 'Reports', 'preview' => 'reports'],
    ],

    // Report lists exactly as on each sheet's REPORTS column (same order as the prototype).
    'reports' => [
        'CRM' => ['Prospects', 'Enquiries', 'Lead ageing report', 'Lead report (lead to PO)'],
        'Sales' => ['Sales order report', 'Sales report', 'Export sales report', 'Credit/Debit report (customer)', 'Stock report and Minimum stock report'],
        'Purchase' => ['PO summary report', 'GRN report', 'Credit/Debit report (supplier)', 'Stock report and Minimum stock report', 'Stock transfer report', 'Stock ledger'],
        'Accounts' => ['Sales report', 'Credit/Debit report (partywise)', 'Export/Import', 'GRN report', 'Party ledger report', 'Outstanding report', 'HSN report', 'Transporter report'],
    ],

    // Reports that are built: group => route name per report, in the order of 'reports' above.
    // Reports not listed here (or listed as null, to keep the positions) still open the prototype's sample download panel.
    // Purchase "Stock report and Minimum stock report" is the Sales one: the Purchase menu opens it with ?in=purchase.
    'report_routes' => [
        'CRM' => ['reports.crm.prospects', 'reports.crm.enquiries', 'reports.crm.lead-ageing', 'reports.crm.lead-report'],
        'Sales' => ['reports.sales.orders', 'reports.sales.sales', 'reports.sales.export', 'reports.sales.credit-debit', 'reports.sales.stock'],
        'Purchase' => ['reports.purchase.po-summary', 'reports.purchase.grn', 'reports.purchase.credit-debit', 'reports.sales.stock', 'reports.purchase.stock-transfers', 'reports.purchase.stock-ledger'],
    ],

    // Old sample-screen pages that now have real routes.
    'built_pages' => [
        'quotation' => 'crm.quotations.index',
        'prospect' => 'crm.prospects.index',
        'dashboard' => 'dashboard',
        'approval' => 'admin.approval.edit',
        'config' => 'admin.config.edit',
        'pagesetup' => 'admin.page-setup.index',
        'masters' => 'admin.masters.index',
        'sales' => 'sales.invoices.index',
        'transfer' => 'purchase.stock-transfers.index',
        'po' => 'purchase.orders.index',
        'grn' => 'purchase.grns.index',
    ],

    'report_perms' => [
        'CRM' => 'reports-crm.view',
        'Sales' => 'reports-sales.view',
        'Purchase' => 'reports-purchase.view',
        'Accounts' => 'reports-accounts.view',
    ],

    // Units = company plants (decision D10). Sample names until the client gives theirs.
    'units' => [
        ['code' => 'U1', 'name' => 'Unit 1 · Chakan'],
        ['code' => 'U2', 'name' => 'Unit 2 · Bhosari'],
    ],

    /*
    | Optional parts of the CRM flow (Sat 14/10/2026). Admin > Page setup turns each on or off for the whole
    | installation, so the same app serves clients that do not do site visits, BOQs or costing. Nursery uses
    | all three. A part that is off disappears from the menu, Access rights, the enquiry workflow, the
    | quotation page, alerts, reports and exports, and its pages are refused; what is already saved is kept
    | and shows again when it is switched back on.
    */
    'modules' => [
        // CRM as a switchable module (01/11/2026). Separate PO menu removed (02/11/2026): its 'po' switch is gone, so POs
        // are entered only in CRM › 6 PO / Agreement and go with CRM.
        'crm' => ['label' => 'CRM', 'default' => true,
            'hint' => 'Enquiries, site visits, quotations, approval, PO / agreements, prospects, contacts, CRM reports, alerts and dashboard.',
            'off' => 'Everyone loses the CRM menu (with CRM › PO / Agreement), its pages, reports, alerts and dashboard. POs cannot be entered while CRM is off.'],
        'site_visit' => ['label' => 'Site Visit / Requirement Gathering', 'default' => true,
            'hint' => 'Site visit page and list, site photos, the Site visit step of the enquiry workflow and the Site visits due alert.',
            'off' => 'Everyone loses the Site visit page and list, site photos, the Site visit step and the Site visits due alert.'],
        'boq' => ['label' => 'BOQ Preparation', 'default' => true,
            'hint' => 'BOQ documents on quotations, sent by email with the quotation.',
            'off' => 'BOQ files are hidden on quotations and are no longer sent with quotation emails.'],
        'costing' => ['label' => 'Cost details (costing)', 'default' => true,
            'hint' => 'Internal cost details on quotations, needed before a quotation goes for approval.',
            'off' => 'The Cost details box is hidden on quotations, and quotations go for approval without costs. Changing this switch also needs Approval · Edit.'],
    ],

    // Admin > Config (22/10/2026): installation-wide settings, saved in app_settings. No row = this default.
    // upload_max_kb: maximum size of a file upload on a form, in KB (shown in MB); see App\Support\UploadLimit.
    'settings' => [
        'upload_max_kb' => ['label' => 'Maximum upload size', 'default' => 1024],
    ],

    /*
    | Admin > Page setup (CRM changes, Sat 13/10/2026; every page, Sat 14/10/2026). One entry per entry page:
    | which fields show and which are mandatory. A new page plugs in here: list its fields, then call
    | PageSetupService::rules() in its Form Request and PageSetupService::form() in its view.
    |   group    heading on the Page setup list (CRM, Masters, and later Sales, Purchase, Accounts)
    |   module   optional part the page belongs to; the page is left out while that part is off
    |   fields   field => label, required (the default), fixed (always shown and mandatory),
    |            inputs (the form inputs the field covers, when not just the field itself),
    |            checked (mandatory is checked by the Form Request itself, e.g. customer or prospect),
    |            can_require => false (tick boxes and the like: Show only, never Mandatory)
    | A field the database needs on every row (NOT NULL) or the app's rules depend on is fixed.
    */
    'page_fields' => [
        'enquiry' => [
            'label' => 'Enquiry',
            'group' => 'CRM',
            'module' => 'crm',
            'fields' => [
                'enquiry_date' => ['label' => 'Enquiry date', 'required' => true, 'fixed' => true],
                'lead_source_id' => ['label' => 'Source', 'required' => true],
                'sub_source' => ['label' => 'Sub-source / Reference', 'required' => false],
                'competitor_details' => ['label' => 'Competitor details', 'required' => false],
                'prospect_type' => ['label' => 'Prospect type', 'required' => true],
                'client' => ['label' => 'Company / Client name', 'required' => true, 'fixed' => true, 'inputs' => ['party_id', 'prospect_id'], 'checked' => true],
                'industry' => ['label' => 'Industry', 'required' => false],
                'industry_type_id' => ['label' => 'Industry type', 'required' => true],
                'city_id' => ['label' => 'City', 'required' => true],
                'address' => ['label' => 'Address', 'required' => false],
                'website' => ['label' => 'Website', 'required' => false],
                'contact' => ['label' => 'Contact name and no', 'required' => true, 'inputs' => ['contact_name', 'contact_no']],
                'service_category_id' => ['label' => 'Service type', 'required' => true],
                'approx_requirement' => ['label' => 'Approx. requirement', 'required' => false],
                'estimated_value' => ['label' => 'Estimated value', 'required' => false],
                'expected_closing_date' => ['label' => 'Expected closing date', 'required' => false],
                'lead_rating' => ['label' => 'Lead rating', 'required' => true],
                'assigned_to' => ['label' => 'Lead assigned to', 'required' => true],
                'next_action' => ['label' => 'Next action and date', 'required' => true, 'inputs' => ['next_action', 'next_action_date']],
                'remarks' => ['label' => 'Remarks', 'required' => false],
            ],
        ],
        // Follow-ups box of the enquiry page (22/10/2026). The next action and its target date go together: a next
        // action becomes the enquiry's next action and its date the next action date.
        'enquiry-followup' => [
            'label' => 'Enquiry follow-up',
            'group' => 'CRM',
            'module' => 'crm',
            'fields' => [
                'activity_type' => ['label' => 'Follow-up by', 'required' => true, 'fixed' => true],
                'activity_date' => ['label' => 'Date', 'required' => true, 'fixed' => true],
                'details' => ['label' => 'Follow-up details', 'required' => true],
                'next_action' => ['label' => 'Next action and target date', 'required' => false, 'inputs' => ['followup_action', 'target_date']],
            ],
        ],
        'site-visit' => [
            'label' => 'Site visit',
            'group' => 'CRM',
            'module' => ['crm', 'site_visit'],
            'fields' => [
                'enquiry_id' => ['label' => 'Client (enquiry)', 'required' => true, 'fixed' => true],
                'visit_date' => ['label' => 'Visit date', 'required' => true, 'fixed' => true],
                'visit_time' => ['label' => 'Visit time', 'required' => false],
                'status' => ['label' => 'Status', 'required' => true, 'fixed' => true],
                'poc' => ['label' => 'POC (person on site)', 'required' => false],
                'technical_employee_id' => ['label' => 'Technical person', 'required' => false],
                'site_address' => ['label' => 'Site address', 'required' => false],
                'area_measurements' => ['label' => 'Area / measurements', 'required' => false],
                'floors' => ['label' => 'Floors', 'required' => false],
                'indoor_outdoor_area' => ['label' => 'Indoor / outdoor area', 'required' => false],
                'lighting_condition' => ['label' => 'Lighting condition', 'required' => false],
                'water_availability' => ['label' => 'Water availability', 'required' => false],
                'access_restrictions' => ['label' => 'Access restrictions', 'required' => false],
                'existing_plants' => ['label' => 'Existing plants', 'required' => false],
                'existing_planters' => ['label' => 'Existing planters', 'required' => false],
                'customer_requirement' => ['label' => 'Customer requirement', 'required' => false],
                'remarks' => ['label' => 'Remarks', 'required' => false],
                'next_action' => ['label' => 'Next action', 'required' => false],
            ],
        ],
        'quotation' => [
            'label' => 'Quotation',
            'group' => 'CRM',
            'module' => 'crm',
            'fields' => [
                'enquiry_id' => ['label' => 'Enquiry (client)', 'required' => true, 'fixed' => true],
                'contact_name' => ['label' => 'Contact', 'required' => false],
                'site' => ['label' => 'Site', 'required' => false],
                'quotation_date' => ['label' => 'Date', 'required' => true, 'fixed' => true],
                'valid_until' => ['label' => 'Valid until', 'required' => false],
                'sales_person_id' => ['label' => 'Sales person', 'required' => true, 'fixed' => true],
                'currency_id' => ['label' => 'Currency', 'required' => true, 'fixed' => true],
                'payment_terms' => ['label' => 'Payment terms', 'required' => false],
                'delivery_terms' => ['label' => 'Delivery / service terms', 'required' => false],
                // GST / tax is a Tax master drop-down (23/10/2026); the key stays so saved show / mandatory choices still apply.
                'tax_terms' => ['label' => 'GST / tax', 'required' => false, 'inputs' => ['tax_structure_id']],
                'notes' => ['label' => 'Notes', 'required' => false],
            ],
        ],
        'customer-po' => [
            'label' => 'PO / Agreement received',
            // Separate PO menu removed (02/11/2026): a CRM page again, with the Quotation fixed and mandatory as before
            // 29/10/2026 (no Customer or "Link to CRM" row).
            'group' => 'CRM',
            'module' => 'crm',
            'fields' => [
                'quotation_id' => ['label' => 'Quotation', 'required' => true, 'fixed' => true],
                'po_no' => ['label' => 'PO / Agreement no', 'required' => true, 'fixed' => true],
                'po_date' => ['label' => 'PO date', 'required' => true, 'fixed' => true],
                'site' => ['label' => 'Site', 'required' => false],
                // Mandatory when the PO has no items; with items it is their sum (01/11/2026).
                'po_value' => ['label' => 'PO value (when no items)', 'required' => true, 'fixed' => true],
                'po_quantity' => ['label' => 'PO quantity', 'required' => false],
                'validity' => ['label' => 'Validity start and end date', 'required' => false, 'inputs' => ['valid_from', 'valid_to']],
                'payment_terms' => ['label' => 'Payment terms', 'required' => false],
                'tax_terms' => ['label' => 'GST / tax', 'required' => false],
                'contact_name' => ['label' => 'Customer contact', 'required' => false],
                'received_by' => ['label' => 'Received by', 'required' => true],
                'received_date' => ['label' => 'Received date', 'required' => true, 'fixed' => true],
                'remarks' => ['label' => 'Remarks', 'required' => false],
                // Items from the Item master (29/10/2026; the key is kept so saved choices still apply): shown and optional
                // by default, when a PO needs items or a typed PO value; Mandatory = at least one item; hidden = items are not
                // read and the PO value is typed. Checked by the request.
                'crm_lines' => ['label' => 'Items (Part master)', 'required' => false, 'inputs' => ['crm_lines']],
            ],
        ],
        'prospect' => [
            'label' => 'Prospect',
            'group' => 'CRM',
            'module' => 'crm',
            'fields' => [
                'name' => ['label' => 'Prospect name', 'required' => true, 'fixed' => true],
                'market' => ['label' => 'Domestic / Export', 'required' => true, 'fixed' => true],
                'contact_person' => ['label' => 'Contact person', 'required' => false],
                'position' => ['label' => 'Position', 'required' => false],
                'phone_1' => ['label' => 'Contact no 1', 'required' => true, 'fixed' => true],
                'phone_2' => ['label' => 'Contact no 2', 'required' => false],
                'email' => ['label' => 'Email', 'required' => false],
                'gstin' => ['label' => 'GSTIN', 'required' => false],
                'industry_type' => ['label' => 'Industry type', 'required' => false],
                'address' => ['label' => 'Address', 'required' => false],
            ],
        ],
        'contact' => [
            'label' => 'Contact / POC',
            'group' => 'CRM',
            'module' => 'crm',
            'fields' => [
                'client' => ['label' => 'Company / Client', 'required' => true, 'fixed' => true, 'inputs' => ['party_id', 'prospect_id'], 'checked' => true],
                'name' => ['label' => 'Name', 'required' => true, 'fixed' => true],
                'designation' => ['label' => 'Designation', 'required' => false],
                'department' => ['label' => 'Department', 'required' => false],
                'mobile' => ['label' => 'Mobile', 'required' => true],
                'whatsapp' => ['label' => 'WhatsApp', 'required' => false],
                'alternate_no' => ['label' => 'Alternate number', 'required' => false],
                'email' => ['label' => 'Email', 'required' => false],
                'preferred_communication' => ['label' => 'Preferred communication', 'required' => false],
                'roles' => ['label' => 'Decision maker, influencer and contact roles', 'required' => false,
                    'inputs' => ['is_decision_maker', 'is_influencer', 'is_purchase_contact', 'is_facility_contact', 'is_billing_contact'], 'can_require' => false],
                'notes' => ['label' => 'Notes', 'required' => false],
            ],
        ],
        'employee' => [
            'label' => 'Employee master',
            'group' => 'Masters',
            'fields' => [
                'code' => ['label' => 'Employee code', 'required' => true, 'fixed' => true],
                'name' => ['label' => 'Name', 'required' => true, 'fixed' => true],
                'designation' => ['label' => 'Designation', 'required' => false],
                'department' => ['label' => 'Department', 'required' => false],
                'mobile' => ['label' => 'Mobile', 'required' => false],
                'email' => ['label' => 'Email', 'required' => false],
                'joining_date' => ['label' => 'Date of joining', 'required' => false],
                'user_id' => ['label' => 'Login user', 'required' => false],
                'status' => ['label' => 'Status', 'required' => true, 'fixed' => true],
                'address' => ['label' => 'Address', 'required' => false],
            ],
        ],
        // The four simple lists: Name is always needed; Order in dropdowns can be hidden or made mandatory.
        'master-sources' => ['label' => 'Source master', 'group' => 'Masters', 'fields' => [
            'name' => ['label' => 'Name', 'required' => true, 'fixed' => true],
            'sort_order' => ['label' => 'Order in dropdowns', 'required' => false],
        ]],
        'master-categories' => ['label' => 'Category master', 'group' => 'Masters', 'fields' => [
            'name' => ['label' => 'Name', 'required' => true, 'fixed' => true],
            'sort_order' => ['label' => 'Order in dropdowns', 'required' => false],
        ]],
        'master-industry-types' => ['label' => 'Industry type master', 'group' => 'Masters', 'fields' => [
            'name' => ['label' => 'Name', 'required' => true, 'fixed' => true],
            'sort_order' => ['label' => 'Order in dropdowns', 'required' => false],
        ]],
        'master-cities' => ['label' => 'City master', 'group' => 'Masters', 'fields' => [
            'name' => ['label' => 'Name', 'required' => true, 'fixed' => true],
            'sort_order' => ['label' => 'Order in dropdowns', 'required' => false],
        ]],

        // ---- Sales module (15/10/2026) ----
        'sales-order' => [
            'label' => 'Sales order',
            'group' => 'Sales',
            'fields' => [
                'party_id' => ['label' => 'Party name', 'required' => true, 'fixed' => true],
                'po_number' => ['label' => 'PO number', 'required' => true, 'fixed' => true],
                'start_date' => ['label' => 'Start date', 'required' => true, 'fixed' => true],
                'end_date' => ['label' => 'End date', 'required' => false],
                'revision' => ['label' => 'Rev no and Rev date', 'required' => false, 'inputs' => ['revision_no', 'revision_date']],
            ],
        ],
        'tax-invoice' => [
            'label' => 'Tax invoice',
            'group' => 'Sales',
            'fields' => [
                'party_id' => ['label' => 'Party name', 'required' => true, 'fixed' => true],
                'invoice_type' => ['label' => 'Invoice type', 'required' => true, 'fixed' => true],
                'invoice_date' => ['label' => 'Invoice date', 'required' => true, 'fixed' => true],
                'sales_order_id' => ['label' => 'PO no', 'required' => false],
                'shipping_address' => ['label' => 'Shipping address', 'required' => true, 'fixed' => true],
                'mode_of_transport' => ['label' => 'Mode of transport', 'required' => false],
                'transporter_id' => ['label' => 'Transporter', 'required' => false],
                'vehicle_no' => ['label' => 'Vehicle no', 'required' => false],
                'distance_km' => ['label' => 'Distance (km)', 'required' => false],
                'pf_charges' => ['label' => 'P&F charges', 'required' => false],
            ],
        ],
        'party' => [
            'label' => 'Party master',
            'group' => 'Masters',
            'fields' => [
                'name' => ['label' => 'Name', 'required' => true, 'fixed' => true],
                'gstin' => ['label' => 'GST no', 'required' => false],
                'type' => ['label' => 'Type', 'required' => true, 'fixed' => true],
                'billing_address' => ['label' => 'Billing address', 'required' => true],
                'shipping_address' => ['label' => 'Shipping address', 'required' => false],
                'payment_terms' => ['label' => 'Payment terms', 'required' => false],
                'opening' => ['label' => 'Opening balance and DR / CR', 'required' => false, 'inputs' => ['opening_balance', 'opening_balance_type']],
                'currency_id' => ['label' => 'Currency', 'required' => false],
                'status' => ['label' => 'Status', 'required' => true, 'fixed' => true],
            ],
        ],
        'item' => [
            'label' => 'Item master',
            'group' => 'Masters',
            'fields' => [
                'party_id' => ['label' => 'Party name', 'required' => false],
                'item_category_id' => ['label' => 'Category', 'required' => false],
                'description' => ['label' => 'Item description', 'required' => true, 'fixed' => true],
                'item_no' => ['label' => 'Item no (blank = automatic from the category prefix)', 'required' => true, 'fixed' => true],
                'internal_item_no' => ['label' => 'Internal item no (IPN)', 'required' => false],
                'type' => ['label' => 'Type', 'required' => true, 'fixed' => true],
                'hsn_sac_code_id' => ['label' => 'HSN/SAC', 'required' => true, 'fixed' => true],
                'uom_id' => ['label' => 'UOM', 'required' => true, 'fixed' => true],
                'tax_structure_id' => ['label' => 'Tax structure', 'required' => true, 'fixed' => true],
                'price' => ['label' => 'Price', 'required' => false],
                'currency_id' => ['label' => 'Currency', 'required' => false],
                'stock_levels' => ['label' => 'Min stock and Max stock', 'required' => false, 'inputs' => ['min_stock', 'max_stock']],
                'weight_per_piece' => ['label' => 'Weight / piece', 'required' => false],
                'packaging_qty' => ['label' => 'Packaging qty', 'required' => false],
                'status' => ['label' => 'Status', 'required' => true, 'fixed' => true],
            ],
        ],
        'company' => ['label' => 'Company', 'group' => 'Masters', 'fields' => [
            'name' => ['label' => 'Name', 'required' => true, 'fixed' => true],
            'gstin' => ['label' => 'GSTIN', 'required' => false],
            'pan' => ['label' => 'PAN', 'required' => false],
            'address' => ['label' => 'Address', 'required' => false],
            'phone' => ['label' => 'Phone', 'required' => false],
            'email' => ['label' => 'Email', 'required' => false],
        ]],
        'master-tax-structures' => ['label' => 'Tax master', 'group' => 'Masters', 'fields' => [
            'name' => ['label' => 'Name', 'required' => true, 'fixed' => true],
            'cgst_rate' => ['label' => 'CGST %', 'required' => true, 'fixed' => true],
            'sgst_rate' => ['label' => 'SGST %', 'required' => true, 'fixed' => true],
            'igst_rate' => ['label' => 'IGST %', 'required' => true, 'fixed' => true],
        ]],
        'master-hsn-sac' => ['label' => 'HSN/SAC master', 'group' => 'Masters', 'fields' => [
            'code' => ['label' => 'Code', 'required' => true, 'fixed' => true],
            'type' => ['label' => 'Type', 'required' => true, 'fixed' => true],
            'description' => ['label' => 'Description', 'required' => false],
            'tax_structure_id' => ['label' => 'Default tax structure', 'required' => false],
        ]],
        'master-uoms' => ['label' => 'UOM master', 'group' => 'Masters', 'fields' => [
            'code' => ['label' => 'Code', 'required' => true, 'fixed' => true],
            'description' => ['label' => 'Description', 'required' => false],
        ]],
        'master-item-categories' => ['label' => 'Category master (items)', 'group' => 'Masters', 'fields' => [
            'name' => ['label' => 'Name', 'required' => true, 'fixed' => true],
            'type' => ['label' => 'Type', 'required' => true, 'fixed' => true],
            'item_no_prefix' => ['label' => 'Item no prefix', 'required' => false],
        ]],
        'master-transporters' => ['label' => 'Transporter master', 'group' => 'Masters', 'fields' => [
            'name' => ['label' => 'Name', 'required' => true, 'fixed' => true],
            'gstin' => ['label' => 'GSTIN', 'required' => false],
        ]],

        // ---- Purchase › Purchase order (27/10/2026). The "Show all headings" fields; items are not field-configurable. ----
        'purchase-order' => [
            'label' => 'Purchase order',
            'group' => 'Purchase',
            'fields' => [
                'party_id' => ['label' => 'Party name', 'required' => true, 'fixed' => true],
                'po_date' => ['label' => 'PO date', 'required' => true, 'fixed' => true],
                'delivery_date' => ['label' => 'Delivery date', 'required' => false],
                'payment_terms' => ['label' => 'Payment terms', 'required' => false],
                'currency_id' => ['label' => 'Currency', 'required' => false],
                'transporter_id' => ['label' => 'Transporter', 'required' => false],
                'billing_address' => ['label' => 'Billing address', 'required' => false],
                'shipping_address' => ['label' => 'Shipping address', 'required' => false],
                'remarks' => ['label' => 'Remarks', 'required' => false],
            ],
        ],

        // ---- Purchase › Stock transfer (27/10/2026). Items (item, quantity, remarks) are not field-configurable. ----
        'stock-transfer' => [
            'label' => 'Stock transfer',
            'group' => 'Purchase',
            'fields' => [
                'transfer_date' => ['label' => 'Transfer date', 'required' => true, 'fixed' => true],
                'from_unit_id' => ['label' => 'Location from', 'required' => true, 'fixed' => true],
                'to_unit_id' => ['label' => 'Location to', 'required' => true, 'fixed' => true],
                'source_type' => ['label' => 'Source type', 'required' => false],
                'remarks' => ['label' => 'Remarks', 'required' => false],
            ],
        ],

        // ---- Purchase › GRN entry (27/10/2026), prototype FIELDS.grn / MANDATORY.grn. Items are not field-configurable. ----
        // Quality approval hidden = no quality step: a matched GRN is approved at once and its stock comes in.
        'grn' => [
            'label' => 'GRN entry',
            'group' => 'Purchase',
            'fields' => [
                'party_id' => ['label' => 'Party name', 'required' => true, 'fixed' => true],
                'invoice_type' => ['label' => 'Invoice type', 'required' => true, 'fixed' => true],
                'invoice_no' => ['label' => 'Invoice no', 'required' => true, 'fixed' => true],
                'invoice_date' => ['label' => 'Invoice date', 'required' => true, 'fixed' => true],
                'invoice_amount' => ['label' => 'Invoice amount', 'required' => true, 'fixed' => true],
                'transporter_id' => ['label' => 'Transporter', 'required' => false],
                'vehicle_no' => ['label' => 'Vehicle no', 'required' => false],
                'eway_bill_no' => ['label' => 'E-way bill no', 'required' => false],
                'eway_bill_date' => ['label' => 'E-way bill date', 'required' => false],
                'quality_status' => ['label' => 'Quality approval', 'required' => false, 'can_require' => false],
                'customer_id' => ['label' => 'Customer name', 'required' => false],
                'sales_order_id' => ['label' => 'SO no', 'required' => false],
                'remarks' => ['label' => 'Remarks', 'required' => false],
            ],
        ],
    ],

    /*
    | GRN entry (27/10/2026). match_tolerance: the lines total (with GST) may differ from the supplier invoice amount by
    | up to this many rupees and still match (prototype: ±₹1). lines_without_po: Purchase invoice lines may be entered
    | without a PO line (prototype PURCHASE A20 lists all the supplier's parts then); off until the client confirms.
    */
    'grn' => [
        'match_tolerance' => 1.00,
        'lines_without_po' => false,
    ],

    /*
    | Masters kept through the shared master screen (Sales module, 15/10/2026): one list with an add / edit form
    | beside it. Field types: text (max), code (upper case, max), percent, enum (options), select (model, label).
    | unique: the value cannot repeat (with unique_with: together with that field). used: table.column that
    | point at a row; a row in use cannot be deleted, only made inactive.
    */
    'sales_masters' => [
        'tax-structures' => ['title' => 'Tax master', 'item' => 'tax structure', 'model' => \App\Models\TaxStructure::class, 'search' => 'name',
            'used' => ['items.tax_structure_id', 'hsn_sac_codes.tax_structure_id', 'quotations.tax_structure_id', 'quotation_lines.tax_structure_id', 'purchase_order_lines.tax_structure_id', 'grn_lines.tax_structure_id'],
            'fields' => [
                'name' => ['label' => 'Name', 'type' => 'text', 'max' => 50, 'unique' => true],
                'cgst_rate' => ['label' => 'CGST %', 'type' => 'percent'],
                'sgst_rate' => ['label' => 'SGST %', 'type' => 'percent'],
                'igst_rate' => ['label' => 'IGST %', 'type' => 'percent'],
            ]],
        'hsn-sac' => ['title' => 'HSN/SAC master', 'item' => 'HSN/SAC code', 'model' => \App\Models\HsnSacCode::class, 'search' => 'code',
            'used' => ['items.hsn_sac_code_id'],
            'fields' => [
                'code' => ['label' => 'Code', 'type' => 'code', 'max' => 10, 'unique' => true, 'unique_with' => 'type'],
                'type' => ['label' => 'Type', 'type' => 'enum', 'options' => ['HSN', 'SAC']],
                'description' => ['label' => 'Description', 'type' => 'text', 'max' => 255],
                'tax_structure_id' => ['label' => 'Default tax structure', 'type' => 'select', 'model' => \App\Models\TaxStructure::class],
            ]],
        'uoms' => ['title' => 'UOM master', 'item' => 'unit of measure', 'model' => \App\Models\Uom::class, 'search' => 'code',
            'used' => ['items.uom_id', 'purchase_order_lines.uom_id', 'grn_lines.uom_id', 'customer_po_lines.uom_id'], // customer PO items: tester review (29/10/2026)
            'fields' => [
                'code' => ['label' => 'Code', 'type' => 'code', 'max' => 10, 'unique' => true],
                'description' => ['label' => 'Description', 'type' => 'text', 'max' => 100],
            ]],
        'item-categories' => ['title' => 'Category master (items)', 'item' => 'category', 'model' => \App\Models\ItemCategory::class, 'search' => 'name',
            'used' => ['items.item_category_id'],
            'fields' => [
                'name' => ['label' => 'Name', 'type' => 'text', 'max' => 100, 'unique' => true],
                'type' => ['label' => 'Type', 'type' => 'enum', 'options' => ['Sales', 'Purchase', 'Both']],
                'item_no_prefix' => ['label' => 'Item no prefix', 'type' => 'code', 'max' => 10],
            ]],
        'transporters' => ['title' => 'Transporter master', 'item' => 'transporter', 'model' => \App\Models\Transporter::class, 'search' => 'name',
            'used' => ['sales_invoices.transporter_id', 'purchase_orders.transporter_id', 'grns.transporter_id'],
            'fields' => [
                'name' => ['label' => 'Name', 'type' => 'text', 'max' => 150, 'unique' => true],
                'gstin' => ['label' => 'GSTIN', 'type' => 'gstin'],
            ]],
    ],

    // Contact / POC: how the contact likes to be reached (the follow-up kinds of the Word file).
    'contact_channels' => ['Call', 'WhatsApp', 'Email'],

    // Disk for uploaded documents: "local" (private storage) on the development PC, a shared private
    // S3-compatible bucket in production so every app server sees the same files (docs/PRODUCTION.md).
    'documents_disk' => env('DOCUMENTS_DISK', 'local'),

    // Documents & attachments: the kinds of document each record takes.
    'document_categories' => [
        'enquiry' => ['Enquiry document', 'Visiting card', 'Requirement email', 'Other'],
        'site_visit' => ['Site photo', 'Other'],
        'quotation' => ['BOQ', 'Costing approval', 'Quotation', 'Other'],
        'customer_po' => ['Customer PO', 'Agreement', 'Other'],
        // Upload PO on the sales order (SALES G11): PDF, JPG or PNG, up to 10 MB (S7).
        'sales_order' => ['Customer PO', 'Other'],
    ],

    // Document types that belong to an optional part: not offered, listed or emailed while that part is off.
    'document_category_modules' => ['BOQ' => 'boq', 'Costing approval' => 'costing'],

    'date_format' => 'd/m/Y',
    'datetime_format' => 'd/m/Y H:i',
];
