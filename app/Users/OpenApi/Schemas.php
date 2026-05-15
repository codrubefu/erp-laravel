<?php

namespace App\Users\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Location',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Main Office'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Headquarters'),
        new OA\Property(property: 'users_count', type: 'integer', example: 5),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StoreLocationRequest',
    required: ['name'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Main Office'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Headquarters'),
        new OA\Property(
            property: 'user_ids',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 2],
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UpdateLocationRequest',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Main Office'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Headquarters'),
        new OA\Property(
            property: 'user_ids',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 2],
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Group',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'admin'),
        new OA\Property(property: 'label', type: 'string', example: 'Administrator'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Full application access.'),
        new OA\Property(
            property: 'rights',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Right'),
        ),
        new OA\Property(property: 'users_count', type: 'integer', example: 3),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Right',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'users.view'),
        new OA\Property(property: 'label', type: 'string', example: 'View users'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Read user records.'),
        new OA\Property(property: 'groups_count', type: 'integer', example: 2),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StoreRightRequest',
    required: ['name', 'label'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'reports.view'),
        new OA\Property(property: 'label', type: 'string', example: 'View reports'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Read report data.'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UpdateRightRequest',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'reports.view'),
        new OA\Property(property: 'label', type: 'string', example: 'View reports'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Read report data.'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StoreGroupRequest',
    required: ['name', 'label'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'manager'),
        new OA\Property(property: 'label', type: 'string', example: 'Manager'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Can view operational data.'),
        new OA\Property(
            property: 'right_ids',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 2, 3],
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UpdateGroupRequest',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'manager'),
        new OA\Property(property: 'label', type: 'string', example: 'Manager'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Can view operational data.'),
        new OA\Property(
            property: 'right_ids',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 2, 3],
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 35),
        new OA\Property(property: 'first_name', type: 'string', example: 'John'),
        new OA\Property(property: 'last_name', type: 'string', example: 'Doe'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+15550001111'),
        new OA\Property(property: 'active', type: 'boolean', example: true),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
        new OA\Property(property: 'email_verified_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(
            property: 'groups',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Group'),
        ),
        new OA\Property(
            property: 'locations',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Location'),
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StoreUserRequest',
    required: ['first_name', 'last_name', 'email', 'password'],
    properties: [
        new OA\Property(property: 'first_name', type: 'string', example: 'John'),
        new OA\Property(property: 'last_name', type: 'string', example: 'Doe'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+15550001111'),
        new OA\Property(property: 'active', type: 'boolean', example: true),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
        new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password'),
        new OA\Property(
            property: 'group_ids',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 2],
        ),
        new OA\Property(
            property: 'location_ids',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 2],
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UpdateUserRequest',
    properties: [
        new OA\Property(property: 'first_name', type: 'string', example: 'John'),
        new OA\Property(property: 'last_name', type: 'string', example: 'Doe'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+15550001111'),
        new OA\Property(property: 'active', type: 'boolean', example: true),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
        new OA\Property(property: 'password', type: 'string', format: 'password', nullable: true, example: 'new-password'),
        new OA\Property(
            property: 'group_ids',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 2],
        ),
        new OA\Property(
            property: 'location_ids',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 2],
        ),
    ],
    type: 'object',
)]
class Schemas
{
}
