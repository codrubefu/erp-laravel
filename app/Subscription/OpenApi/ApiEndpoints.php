<?php

namespace App\Subscription\OpenApi;

use OpenApi\Attributes as OA;

class ApiEndpoints
{
    #[OA\Get(
        path: '/subscriptions',
        summary: 'List subscriptions',
        security: [['bearerAuth' => []]],
        tags: ['Subscription'],
        parameters: [
            new OA\QueryParameter(name: 'search', required: false, schema: new OA\Schema(type: 'string'), example: 'enterprise'),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer'), example: 15),
            new OA\QueryParameter(name: 'is_active', required: false, schema: new OA\Schema(type: 'boolean'), example: true),
            new OA\QueryParameter(name: 'billing_interval', required: false, schema: new OA\Schema(type: 'string', enum: ['monthly', 'yearly']), example: 'monthly'),
            new OA\QueryParameter(name: 'with_trashed', required: false, schema: new OA\Schema(type: 'boolean'), example: false),
            new OA\QueryParameter(name: 'only_trashed', required: false, schema: new OA\Schema(type: 'boolean'), example: false),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated subscription list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Subscription'),
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing subscriptions.view or subscriptions.manage right.'),
        ],
    )]
    public function index(): void
    {
    }

    #[OA\Post(
        path: '/subscriptions',
        summary: 'Create a subscription',
        security: [['bearerAuth' => []]],
        tags: ['Subscription'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreSubscriptionRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Subscription created.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Subscription'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing subscriptions.create or subscriptions.manage right.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function store(): void
    {
    }

    #[OA\Get(
        path: '/subscriptions/{subscription}',
        summary: 'Show a subscription',
        security: [['bearerAuth' => []]],
        tags: ['Subscription'],
        parameters: [
            new OA\PathParameter(name: 'subscription', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Subscription details.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Subscription'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing subscriptions.view or subscriptions.manage right.'),
            new OA\Response(response: 404, description: 'Subscription not found.'),
        ],
    )]
    public function show(): void
    {
    }

    #[OA\Patch(
        path: '/subscriptions/{subscription}',
        summary: 'Update a subscription',
        security: [['bearerAuth' => []]],
        tags: ['Subscription'],
        parameters: [
            new OA\PathParameter(name: 'subscription', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateSubscriptionRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Subscription updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Subscription'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing subscriptions.update or subscriptions.manage right.'),
            new OA\Response(response: 404, description: 'Subscription not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function update(): void
    {
    }

    #[OA\Put(
        path: '/subscriptions/{subscription}',
        summary: 'Replace a subscription',
        security: [['bearerAuth' => []]],
        tags: ['Subscription'],
        parameters: [
            new OA\PathParameter(name: 'subscription', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateSubscriptionRequest'),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Subscription updated.'),
            new OA\Response(response: 403, description: 'Missing subscriptions.update or subscriptions.manage right.'),
            new OA\Response(response: 404, description: 'Subscription not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function replace(): void
    {
    }

    #[OA\Delete(
        path: '/subscriptions/{subscription}',
        summary: 'Delete a subscription',
        security: [['bearerAuth' => []]],
        tags: ['Subscription'],
        parameters: [
            new OA\PathParameter(name: 'subscription', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Subscription deleted.'),
            new OA\Response(response: 403, description: 'Missing subscriptions.delete or subscriptions.manage right.'),
            new OA\Response(response: 404, description: 'Subscription not found.'),
        ],
    )]
    public function destroy(): void
    {
    }

    #[OA\Post(
        path: '/subscriptions/{subscription}/restore',
        summary: 'Restore a deleted subscription',
        security: [['bearerAuth' => []]],
        tags: ['Subscription'],
        parameters: [
            new OA\PathParameter(name: 'subscription', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Subscription restored.'),
            new OA\Response(response: 403, description: 'Missing subscriptions.restore or subscriptions.manage right.'),
            new OA\Response(response: 404, description: 'Subscription not found.'),
        ],
    )]
    public function restore(): void
    {
    }

    #[OA\Patch(
        path: '/subscriptions/{subscription}/toggle-active',
        summary: 'Toggle subscription active status',
        security: [['bearerAuth' => []]],
        tags: ['Subscription'],
        parameters: [
            new OA\PathParameter(name: 'subscription', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Subscription active status toggled.'),
            new OA\Response(response: 403, description: 'Missing subscriptions.update or subscriptions.manage right.'),
            new OA\Response(response: 404, description: 'Subscription not found.'),
        ],
    )]
    public function toggleActive(): void
    {
    }
}
