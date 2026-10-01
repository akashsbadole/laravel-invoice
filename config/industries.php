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
        'item_fields' => ['metal_type', 'purity', 'huid_number', 'stone_clarity', 'stone_color', 'stone_carat', 'certificate_number', 'gross_weight', 'net_weight', 'stone_weight', 'size_label', 'finish', 'grade', 'specification', 'unit_label', 'color', 'material', 'manufacturer', 'country_of_origin', 'warranty_months'],
        'template_flags' => ['show_huid', 'show_stone_details'],
        'charge_types' => ['Making Charge', 'Wastage', 'Stone Charge', 'Hallmarking Charge', 'Certification Charge', 'Polish / Rhodium Charge', 'Packing', 'Delivery'],
    ],

    'hardware' => [
        'label' => 'Hardware & Building Materials',
        'description' => 'Tools, fittings and building materials sold per piece, per kg or per running unit.',
        'uses_metal_rates' => false,
        'uses_weight_fields' => false,
        'uses_stone_fields' => false,
        'document_type' => 'general_invoice',
        'pricing_mode' => 'manual',
        'rate_types' => ['per_piece', 'per_kg', 'fixed'],
        'item_fields' => ['brand', 'model_number', 'serial_number', 'warranty_months', 'unit_label', 'color', 'material', 'thickness', 'manufacturer', 'country_of_origin', 'size_label', 'grade', 'specification', 'pack_size'],
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
        'item_fields' => ['brand', 'size_label', 'finish', 'grade', 'batch_number', 'boxes', 'wastage_percent', 'unit_label', 'color', 'material', 'thickness', 'manufacturer', 'country_of_origin', 'pack_size', 'specification'],
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

];
