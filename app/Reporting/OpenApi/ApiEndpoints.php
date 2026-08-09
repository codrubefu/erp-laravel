<?php

namespace App\Reporting\OpenApi;

use OpenApi\Attributes as OA;

class ApiEndpoints
{
    #[OA\Get(
        path: '/reports/financial',
        summary: 'Aggregate the financial report',
        description: 'Runs an organization-scoped financial aggregation. This expensive operation is rate limited by client IP, authenticated organization and user identity.',
        security: [['bearerAuth' => []]],
        tags: ['Reporting'],
        parameters: [
            new OA\QueryParameter(name: 'from', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\QueryParameter(name: 'to', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\QueryParameter(name: 'location_id', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'admin_id', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'group_by', required: false, schema: new OA\Schema(type: 'string', enum: ['day', 'month'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Organization-scoped financial totals and series.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Missing reports.view right or a cross-organization filter was requested.'),
            new OA\Response(response: 422, description: 'Invalid report filters.'),
            new OA\Response(response: 429, description: 'Expensive-endpoint rate limit exceeded.'),
        ],
    )]
    public function financialReport(): void
    {
    }

    #[OA\Post(
        path: '/reports/financial/exports',
        summary: 'Queue a financial report export',
        description: 'Queues a CSV or XLSX export. Export creation is protected by the expensive-endpoint rate limit.',
        security: [['bearerAuth' => []]],
        tags: ['Reporting'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['format'],
                properties: [new OA\Property(property: 'format', type: 'string', enum: ['csv', 'xlsx'])],
                type: 'object',
            ),
        ),
        responses: [
            new OA\Response(response: 202, description: 'Export queued.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Missing reports.export right.'),
            new OA\Response(response: 422, description: 'Invalid export format or filters.'),
            new OA\Response(response: 429, description: 'Expensive-endpoint rate limit exceeded.'),
        ],
    )]
    public function financialExport(): void
    {
    }

    #[OA\Get(
        path: '/segments/{segment}/members',
        summary: 'Evaluate members of a saved segment',
        description: 'Returns organization-scoped matching members. Segment evaluation is protected by the expensive-endpoint rate limit.',
        security: [['bearerAuth' => []]],
        tags: ['Reporting'],
        parameters: [
            new OA\PathParameter(name: 'segment', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
            new OA\QueryParameter(name: 'page', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated matching users.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Missing segments.view or segments.manage right.'),
            new OA\Response(response: 404, description: 'Segment not found in the authenticated organization.'),
            new OA\Response(response: 429, description: 'Expensive-endpoint rate limit exceeded.'),
        ],
    )]
    public function segmentMembers(): void
    {
    }
}
