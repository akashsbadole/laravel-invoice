<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Industries
    |--------------------------------------------------------------------------
    |
    | Which verticals the app supports. Each entry declares the modules and
    | fields that only apply to that trade, so the UI can hide what a tenant
    | will never use. This list is data, not an enum — add a new industry
    | here (plus a migration for any field it needs) without touching code.
    |
    | Keys are stored on business_settings.industry.
    |
    */

    'jewelry' => [
        'label' => 'Jewelry',
        'description' => 'Gold, silver and gemstone work — weight-based pricing, hallmarking and metal rates.',
        'uses_metal_rates' => true,
        'uses_weight_fields' => true,
        'uses_stone_fields' => true,
        'document_type' => 'jewelry_invoice',
        'pricing_mode' => 'jewelry_calculated',
        'rate_types' => ['per_gram', 'per_carat', 'per_piece', 'fixed'],
        'item_fields' => ['metal_type', 'purity', 'size_label', 'finish', 'grade', 'specification', 'unit_label', 'color', 'material', 'manufacturer', 'country_of_origin', 'warranty_months', 'default_net_weight', 'default_gross_weight'],
        'template_flags' => ['show_huid', 'show_stone_details'],
        'charge_types' => ['Making Charge', 'Wastage', 'Stone Charge', 'Hallmarking Charge', 'Certification Charge', 'Polish / Rhodium Charge', 'Packing', 'Delivery'],
    ],

    'hardware' => [
        'label' => 'Hardware & Building Materials',
        'description' => 'Tools, fittings, paint and building materials sold per piece, per litre, per kg or per running unit.',
        'uses_metal_rates' => false,
        'uses_weight_fields' => false,
        'uses_stone_fields' => false,
        'document_type' => 'general_invoice',
        'pricing_mode' => 'manual',
        'rate_types' => ['per_piece', 'per_litre', 'per_kg', 'per_meter', 'fixed'],
        'item_fields' => ['brand', 'model_number', 'serial_number', 'warranty_months', 'unit_label', 'color', 'material', 'thickness', 'manufacturer', 'country_of_origin', 'size_label', 'grade', 'specification', 'pack_size', 'shade_code', 'volume'],
        'template_flags' => [],
        'charge_types' => ['Freight', 'Installation', 'Loading', 'Packing', 'Other'],
    ],

    'tiles_marble' => [
        'label' => 'Tiles, Marble & Stone',
        'description' => 'Area-based pricing — square feet or square metres with wastage and wastage-free areas.',
        'uses_metal_rates' => false,
        'uses_weight_fields' => false,
        'uses_stone_fields' => false,
        'document_type' => 'general_invoice',
        // Area and box pricing are computed from the rate × measure, so this
        // industry needs the calculated path, not manual amounts.
        'pricing_mode' => 'jewelry_calculated',
        'rate_types' => ['per_sqft', 'per_sqm', 'per_box', 'per_piece', 'fixed'],
        'item_fields' => ['brand', 'size_label', 'finish', 'grade', 'batch_number', 'boxes', 'color', 'material', 'thickness', 'manufacturer', 'country_of_origin', 'pack_size', 'specification', 'default_length', 'default_width', 'default_wastage_percent'],
        'template_flags' => [],
        'charge_types' => ['Wastage', 'Loading', 'Installation', 'Packing', 'Delivery', 'Other'],
    ],

    'plumbing_electrical' => [
        'label' => 'Plumbing & Electrical',
        'description' => 'Pipes, fittings, switches and accessories sold per piece or per metre.',
        'uses_metal_rates' => false,
        'uses_weight_fields' => false,
        'uses_stone_fields' => false,
        'document_type' => 'general_invoice',
        'pricing_mode' => 'manual',
        'rate_types' => ['per_piece', 'per_meter', 'per_kg', 'fixed'],
        'item_fields' => ['brand', 'model_number', 'size_label', 'specification', 'warranty_months', 'unit_label', 'color', 'material', 'thickness', 'manufacturer', 'country_of_origin', 'pack_size', 'grade'],
        'template_flags' => [],
        'charge_types' => ['Installation', 'Freight', 'Packing', 'Other'],
    ],

    'general' => [
        'label' => 'General Trade',
        'description' => 'Any product-based business invoicing per piece, per unit or at a fixed price.',
        'uses_metal_rates' => false,
        'uses_weight_fields' => false,
        'uses_stone_fields' => false,
        'document_type' => 'general_invoice',
        'pricing_mode' => 'manual',
        'rate_types' => ['per_piece', 'per_unit', 'fixed'],
        'item_fields' => ['brand', 'unit_label', 'color', 'material', 'size_label', 'grade', 'specification', 'pack_size', 'manufacturer'],
        'template_flags' => [],
        'charge_types' => ['Freight', 'Packing', 'Other'],
    ],

    'furniture' => [
        'label' => 'Furniture & Interiors',
        'description' => 'Sofas, beds, wardrobes and decor sold per piece, per sq ft or at a fixed project price.',
        'uses_metal_rates' => false,
        'uses_weight_fields' => false,
        'uses_stone_fields' => false,
        'document_type' => 'general_invoice',
        // Furniture is often quoted for a whole room, so area pricing matters
        // alongside plain per-piece and project totals.
        'pricing_mode' => 'jewelry_calculated',
        'rate_types' => ['per_piece', 'per_sqft', 'per_sqm', 'fixed'],
        'item_fields' => ['brand', 'model_number', 'size_label', 'material', 'fabric', 'weave', 'pattern', 'finish', 'grade', 'color', 'thickness', 'specification', 'warranty_months', 'pack_size', 'unit_label', 'manufacturer', 'country_of_origin', 'default_length', 'default_width', 'default_wastage_percent'],
        'template_flags' => [],
        'charge_types' => ['Delivery', 'Installation', 'Assembly', 'Packing', 'Site Visit'],
    ],

    'textiles' => [
        'label' => 'Textiles, Sarees & Apparel',
        'description' => 'Fabric, sarees and garments sold per piece, per metre or by weight, with fabric and weave recorded.',
        'uses_metal_rates' => false,
        'uses_weight_fields' => false,
        'uses_stone_fields' => false,
        'document_type' => 'general_invoice',
        'pricing_mode' => 'manual',
        'rate_types' => ['per_piece', 'per_meter', 'per_kg', 'fixed'],
        'item_fields' => ['brand', 'model_number', 'size_label', 'material', 'fabric', 'weave', 'pattern', 'color', 'finish', 'grade', 'specification', 'pack_size', 'unit_label', 'manufacturer', 'country_of_origin', 'warranty_months', 'default_length'],
        'template_flags' => [],
        'charge_types' => ['Hemming', 'Stitching', 'Alteration', 'Packing', 'Delivery'],
    ],

    'electronics' => [
        'label' => 'Electronics & Appliances',
        'description' => 'Devices and appliances tracked by serial number, with manufacturer warranty periods.',
        'uses_metal_rates' => false,
        'uses_weight_fields' => false,
        'uses_stone_fields' => false,
        'document_type' => 'general_invoice',
        'pricing_mode' => 'manual',
        'rate_types' => ['per_piece', 'per_unit', 'fixed'],
        // Serial number is the point of this trade: a warranty claim needs the
        // exact unit, not just the model.
        'item_fields' => ['brand', 'model_number', 'serial_number', 'specification', 'warranty_months', 'color', 'material', 'grade', 'size_label', 'pack_size', 'unit_label', 'manufacturer', 'country_of_origin'],
        'template_flags' => [],
        'charge_types' => ['Delivery', 'Installation', 'Packing', 'Extended Warranty'],
    ],

    'paint' => [
        'label' => 'Paint, Coatings & Hardware Retail',
        'description' => 'Paints, primers and adhesives billed per litre or per kg, with shade codes and coverage.',
        'uses_metal_rates' => false,
        'uses_weight_fields' => false,
        'uses_stone_fields' => false,
        'document_type' => 'general_invoice',
        'pricing_mode' => 'manual',
        'rate_types' => ['per_litre', 'per_kg', 'per_piece', 'fixed'],
        'item_fields' => ['brand', 'model_number', 'shade_code', 'color', 'volume', 'coverage_area', 'material', 'specification', 'grade', 'finish', 'size_label', 'pack_size', 'unit_label', 'manufacturer', 'country_of_origin', 'warranty_months'],
        'template_flags' => [],
        'charge_types' => ['Delivery', 'Application', 'Packing', 'Site Visit'],
    ],

    'contractors' => [
        'label' => 'Contractors & Civil Work',
        'description' => 'Site-based work quoted per site reference or at a fixed milestone value.',
        'uses_metal_rates' => false,
        'uses_weight_fields' => false,
        'uses_stone_fields' => false,
        'document_type' => 'general_invoice',
        'pricing_mode' => 'manual',
        // Milestone billing is what contractors actually invoice against, so
        // area pricing is offered for flooring and tiling work on site.
        'rate_types' => ['per_piece', 'per_sqft', 'per_sqm', 'fixed'],
        'item_fields' => ['service_type', 'site_reference', 'specification', 'material', 'grade', 'size_label', 'color', 'unit_label', 'warranty_months', 'manufacturer', 'default_length', 'default_width', 'default_wastage_percent'],
        'template_flags' => [],
        'charge_types' => ['Labour', 'Material', 'Transport', 'Site Visit', 'Packing'],
    ],

    'auto_parts' => [
        'label' => 'Auto Parts & Accessories',
        'description' => 'Spares and accessories matched to a vehicle, billed per piece with GST-heavy HSN codes.',
        'uses_metal_rates' => false,
        'uses_weight_fields' => false,
        'uses_stone_fields' => false,
        'document_type' => 'general_invoice',
        'pricing_mode' => 'manual',
        'rate_types' => ['per_piece', 'per_kg', 'per_unit', 'fixed'],
        // Fitment is what makes a spare sellable, so it rides on the free-form
        // attributes map ("Fits: Maruti Swift 2018-22") rather than a column
        // that only this trade would ever use.
        'item_fields' => ['brand', 'model_number', 'serial_number', 'specification', 'material', 'grade', 'size_label', 'color', 'finish', 'thickness', 'pack_size', 'unit_label', 'manufacturer', 'country_of_origin', 'warranty_months'],
        'template_flags' => [],
        'charge_types' => ['Loading', 'Installation', 'Freight', 'Packing', 'Other'],
    ],

    'watches_eyewear' => [
        'label' => 'Watches & Eyewear',
        'description' => 'Timepieces and eyewear tracked by serial, with brand warranty and service follow-ups.',
        'uses_metal_rates' => false,
        'uses_weight_fields' => false,
        'uses_stone_fields' => false,
        'document_type' => 'general_invoice',
        'pricing_mode' => 'manual',
        'rate_types' => ['per_piece', 'fixed'],
        'item_fields' => ['brand', 'model_number', 'serial_number', 'specification', 'material', 'color', 'size_label', 'finish', 'grade', 'warranty_months', 'pack_size', 'unit_label', 'manufacturer', 'country_of_origin'],
        'template_flags' => [],
        'charge_types' => ['Delivery', 'Packing', 'Service', 'Other'],
    ],

    'mobile_gadgets' => [
        'label' => 'Mobile & Gadget Shops',
        'description' => 'Handsets and gadgets sold with IMEI/serial capture and EMI-style installment plans.',
        'uses_metal_rates' => false,
        'uses_weight_fields' => false,
        'uses_stone_fields' => false,
        'document_type' => 'general_invoice',
        'pricing_mode' => 'manual',
        'rate_types' => ['per_piece', 'per_unit', 'fixed'],
        // The IMEI belongs per sold unit, so it is captured on the invoice line
        // rather than on the catalog product.
        'item_fields' => ['brand', 'model_number', 'serial_number', 'specification', 'color', 'size_label', 'grade', 'material', 'warranty_months', 'pack_size', 'unit_label', 'manufacturer', 'country_of_origin'],
        'template_flags' => [],
        'charge_types' => ['Delivery', 'Installation', 'Packing', 'Extended Warranty', 'Other'],
    ],

    'modular_kitchen' => [
        'label' => 'Modular Kitchens & Wardrobes',
        'description' => 'Bespoke fitted work quoted by area or per module, with site installation and stage payments.',
        'uses_metal_rates' => false,
        'uses_weight_fields' => false,
        'uses_stone_fields' => false,
        'document_type' => 'general_invoice',
        // Carcass and shutter area drive the quote, so calculated pricing.
        'pricing_mode' => 'jewelry_calculated',
        'rate_types' => ['per_sqft', 'per_sqm', 'per_piece', 'per_meter', 'fixed'],
        'item_fields' => ['brand', 'material', 'color', 'finish', 'grade', 'thickness', 'size_label', 'pattern', 'specification', 'service_type', 'site_reference', 'warranty_months', 'pack_size', 'unit_label', 'manufacturer', 'default_length', 'default_width', 'default_wastage_percent'],
        'template_flags' => [],
        'charge_types' => ['Installation', 'Site Visit', 'Transport', 'Packing', 'Other'],
    ],

    'photography_studio' => [
        'label' => 'Photo, Optical & Creative Studios',
        'description' => 'Session and service packages billed per event or as a fixed project fee.',
        'uses_metal_rates' => false,
        'uses_weight_fields' => false,
        'uses_stone_fields' => false,
        'document_type' => 'general_invoice',
        'pricing_mode' => 'manual',
        'rate_types' => ['per_piece', 'per_unit', 'fixed'],
        'item_fields' => ['service_type', 'specification', 'material', 'grade', 'size_label', 'color', 'pack_size', 'unit_label', 'warranty_months', 'manufacturer'],
        'template_flags' => [],
        'charge_types' => ['Travel', 'Studio Rental', 'Editing', 'Packing', 'Other'],
    ],

    'repair_services' => [
        'label' => 'Repair & Servicing Shops',
        'description' => 'Job-card workflow: a delivery challan goes out for the device, a quotation for the work, then an invoice.',
        'uses_metal_rates' => false,
        'uses_weight_fields' => false,
        'uses_stone_fields' => false,
        'document_type' => 'general_invoice',
        'pricing_mode' => 'manual',
        'rate_types' => ['per_piece', 'per_unit', 'fixed'],
        'item_fields' => ['service_type', 'brand', 'model_number', 'serial_number', 'specification', 'grade', 'warranty_months', 'size_label', 'material', 'manufacturer', 'unit_label'],
        'template_flags' => [],
        'charge_types' => ['Labour', 'Parts', 'Diagnostic', 'Pickup & Drop', 'Other'],
    ],

    'education' => [
        'label' => 'Tuition, Coaching & Training',
        'description' => 'Courses billed as installments with recurring fees and admission paperwork.',
        'uses_metal_rates' => false,
        'uses_weight_fields' => false,
        'uses_stone_fields' => false,
        'document_type' => 'general_invoice',
        'pricing_mode' => 'manual',
        'rate_types' => ['per_piece', 'per_unit', 'fixed'],
        'item_fields' => ['service_type', 'specification', 'grade', 'size_label', 'unit_label', 'pack_size', 'warranty_months', 'manufacturer'],
        'template_flags' => [],
        'charge_types' => ['Material', 'Registration Fee', 'Exam Fee', 'Packing', 'Other'],
    ],

    'it_services' => [
        'label' => 'IT Services & Agencies',
        'description' => 'Project and retainer work billed as service lines, with no stock involved.',
        'uses_metal_rates' => false,
        'uses_weight_fields' => false,
        'uses_stone_fields' => false,
        'document_type' => 'general_invoice',
        'pricing_mode' => 'manual',
        'rate_types' => ['per_piece', 'per_unit', 'per_meter', 'fixed'],
        'item_fields' => ['service_type', 'specification', 'grade', 'unit_label', 'material', 'warranty_months', 'manufacturer'],
        'template_flags' => [],
        'charge_types' => ['Hosting', 'Third-party Licence', 'Support Retainer', 'Travel', 'Other'],
    ],

    'events_interiors' => [
        'label' => 'Interior Designers & Event Planners',
        'description' => 'Quoted proposals with revisions, advance booking amounts and delivery dates.',
        'uses_metal_rates' => false,
        'uses_weight_fields' => false,
        'uses_stone_fields' => false,
        'document_type' => 'general_invoice',
        'pricing_mode' => 'manual',
        'rate_types' => ['per_piece', 'per_sqft', 'fixed'],
        'item_fields' => ['service_type', 'site_reference', 'material', 'color', 'pattern', 'finish', 'grade', 'specification', 'size_label', 'unit_label', 'warranty_months', 'manufacturer', 'default_length', 'default_width'],
        'template_flags' => [],
        'charge_types' => ['Labour', 'Material', 'Rentals', 'Site Visit', 'Transport', 'Other'],
    ],

];
