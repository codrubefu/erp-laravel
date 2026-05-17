<?php

namespace App\Articles\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Article',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'title', type: 'string', example: 'Quarterly Operations Update'),
        new OA\Property(property: 'description', type: 'string', example: 'Summary of operational changes for the quarter.'),
        new OA\Property(property: 'created_by', type: 'integer', example: 1),
        new OA\Property(property: 'author', ref: '#/components/schemas/User'),
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
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StoreArticleRequest',
    required: ['title', 'description'],
    properties: [
        new OA\Property(property: 'title', type: 'string', maxLength: 255, example: 'Quarterly Operations Update'),
        new OA\Property(property: 'description', type: 'string', example: 'Summary of operational changes for the quarter.'),
        new OA\Property(
            property: 'groups',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 2],
        ),
        new OA\Property(
            property: 'locations',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 3],
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UpdateArticleRequest',
    properties: [
        new OA\Property(property: 'title', type: 'string', maxLength: 255, example: 'Quarterly Operations Update'),
        new OA\Property(property: 'description', type: 'string', example: 'Summary of operational changes for the quarter.'),
        new OA\Property(
            property: 'groups',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 2],
        ),
        new OA\Property(
            property: 'locations',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 3],
        ),
    ],
    type: 'object',
)]
class Schemas
{
}