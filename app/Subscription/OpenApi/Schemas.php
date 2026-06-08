<?php

namespace App\Subscription\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Subscription',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Enterprise'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Enterprise subscription'),
        new OA\Property(property: 'price', type: 'string', example: '99.99'),
        new OA\Property(property: 'currency', type: 'string', example: 'EUR'),
        new OA\Property(property: 'organization_id', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'duration_days', type: 'integer', nullable: true, example: 365),
        new OA\Property(property: 'max_users', type: 'integer', nullable: true, example: 25),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'assignment_id', type: 'integer', nullable: true, example: 10),
        new OA\Property(property: 'start_date', type: 'string', format: 'date', nullable: true, example: '2026-05-18'),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date', nullable: true, example: '2026-06-18'),
        new OA\Property(property: 'is_currently_active', type: 'boolean', nullable: true, example: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StoreSubscriptionRequest',
    required: ['name', 'price', 'currency'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Enterprise'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Enterprise subscription'),
        new OA\Property(property: 'price', type: 'number', format: 'float', minimum: 0, example: 99.99),
        new OA\Property(property: 'currency', type: 'string', maxLength: 3, example: 'EUR'),
        new OA\Property(property: 'duration_days', type: 'integer', minimum: 1, nullable: true, example: 365),
        new OA\Property(property: 'max_users', type: 'integer', minimum: 1, nullable: true, example: 25),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UpdateSubscriptionRequest',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Enterprise Plus'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Updated subscription'),
        new OA\Property(property: 'price', type: 'number', format: 'float', minimum: 0, example: 129.99),
        new OA\Property(property: 'currency', type: 'string', maxLength: 3, example: 'EUR'),
        new OA\Property(property: 'duration_days', type: 'integer', minimum: 1, nullable: true, example: 365),
        new OA\Property(property: 'max_users', type: 'integer', minimum: 1, nullable: true, example: 50),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
    ],
    type: 'object',
)]
class Schemas
{
}
