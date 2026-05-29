<?php

namespace App\CustomFields\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CustomFieldType',
    type: 'string',
    enum: [
        'text',
        'textarea',
        'number',
        'date',
        'datetime',
        'email',
        'phone',
        'select',
        'multi_select',
        'checkbox',
        'boolean',
        'file',
    ],
    example: 'select',
)]
#[OA\Schema(
    schema: 'CustomFieldOption',
    properties: [
        new OA\Property(property: 'label', type: 'string', example: 'Lead source'),
        new OA\Property(property: 'value', type: 'string', example: 'lead_source'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'CustomFieldOptions',
    description: 'JSON configuration for custom fields. Select, multi_select, and checkbox fields can provide choices/options.',
    properties: [
        new OA\Property(
            property: 'choices',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/CustomFieldOption'),
            example: [
                ['label' => 'Website', 'value' => 'website'],
                ['label' => 'Referral', 'value' => 'referral'],
            ],
        ),
    ],
    type: 'object',
    nullable: true,
)]
#[OA\Schema(
    schema: 'CustomField',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'organization_id', type: 'integer', example: 1),
        new OA\Property(property: 'entity_type', type: 'string', example: 'contacts'),
        new OA\Property(property: 'name', type: 'string', example: 'Lead Source'),
        new OA\Property(property: 'slug', type: 'string', example: 'lead_source'),
        new OA\Property(property: 'type', ref: '#/components/schemas/CustomFieldType'),
        new OA\Property(property: 'options', ref: '#/components/schemas/CustomFieldOptions'),
        new OA\Property(
            property: 'validation_rules',
            type: 'array',
            items: new OA\Items(type: 'string'),
            nullable: true,
            example: ['max:255'],
        ),
        new OA\Property(property: 'is_required', type: 'boolean', example: true),
        new OA\Property(property: 'sort_order', type: 'integer', example: 10),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StoreCustomFieldRequest',
    required: ['entity_type', 'name', 'slug', 'type'],
    properties: [
        new OA\Property(property: 'entity_type', type: 'string', maxLength: 255, example: 'contacts'),
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Lead Source'),
        new OA\Property(property: 'slug', type: 'string', maxLength: 255, example: 'lead_source'),
        new OA\Property(property: 'type', ref: '#/components/schemas/CustomFieldType'),
        new OA\Property(property: 'options', ref: '#/components/schemas/CustomFieldOptions'),
        new OA\Property(
            property: 'validation_rules',
            type: 'array',
            items: new OA\Items(type: 'string'),
            nullable: true,
            example: ['max:255'],
        ),
        new OA\Property(property: 'is_required', type: 'boolean', example: true),
        new OA\Property(property: 'sort_order', type: 'integer', minimum: 0, example: 10),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UpdateCustomFieldRequest',
    properties: [
        new OA\Property(property: 'entity_type', type: 'string', maxLength: 255, example: 'contacts'),
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Lead Source'),
        new OA\Property(property: 'slug', type: 'string', maxLength: 255, example: 'lead_source'),
        new OA\Property(property: 'type', ref: '#/components/schemas/CustomFieldType'),
        new OA\Property(property: 'options', ref: '#/components/schemas/CustomFieldOptions'),
        new OA\Property(
            property: 'validation_rules',
            type: 'array',
            items: new OA\Items(type: 'string'),
            nullable: true,
            example: ['max:255'],
        ),
        new OA\Property(property: 'is_required', type: 'boolean', example: true),
        new OA\Property(property: 'sort_order', type: 'integer', minimum: 0, example: 10),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'SaveCustomFieldValuesRequest',
    required: ['values'],
    properties: [
        new OA\Property(
            property: 'values',
            description: 'Map of custom field slug to value. Values are validated according to field type and field-specific rules.',
            type: 'object',
            additionalProperties: true,
            example: [
                'lead_source' => 'website',
                'score' => 42,
                'newsletter_opt_in' => true,
                'interests' => ['product_updates', 'events'],
            ],
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'CustomFieldValue',
    properties: [
        new OA\Property(property: 'custom_field', ref: '#/components/schemas/CustomField'),
        new OA\Property(
            property: 'value',
            description: 'Resolved custom field value. Scalar fields return a scalar value; multi_select and checkbox return arrays.',
            nullable: true,
            oneOf: [
                new OA\Schema(type: 'string'),
                new OA\Schema(type: 'number'),
                new OA\Schema(type: 'boolean'),
                new OA\Schema(type: 'array', items: new OA\Items()),
            ],
            example: 'website',
        ),
    ],
    type: 'object',
)]
class Schemas
{
}
