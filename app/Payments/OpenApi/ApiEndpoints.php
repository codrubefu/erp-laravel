<?php

namespace App\Payments\OpenApi;

use OpenApi\Attributes as OA;

class ApiEndpoints
{
    #[OA\Get(
        path: '/payments',
        summary: 'List payments',
        security: [['bearerAuth' => []]],
        tags: ['Payment'],
        parameters: [
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer'), example: 15),
            new OA\QueryParameter(name: 'page', required: false, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated payment list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Payment'),
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing payments.view or payments.manage right.'),
        ],
    )]
    public function index(): void
    {
    }

    #[OA\Post(
        path: '/payments',
        summary: 'Create a payment',
        security: [['bearerAuth' => []]],
        tags: ['Payment'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StorePaymentRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Payment created.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Payment'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing payments.create or payments.manage right.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function store(): void
    {
    }

    #[OA\Patch(
        path: '/payments/{payment}/attach-model',
        summary: 'Attach or update payment model metadata',
        security: [['bearerAuth' => []]],
        tags: ['Payment'],
        parameters: [
            new OA\PathParameter(name: 'payment', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/AttachPaymentModelRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Payment model metadata updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Payment'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing payments.update or payments.manage right.'),
            new OA\Response(response: 404, description: 'Payment not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function attachModel(): void
    {
    }
}