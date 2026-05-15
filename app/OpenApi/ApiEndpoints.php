<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class ApiEndpoints
{
    #[OA\Post(
        path: '/login',
        summary: 'Login and issue a bearer token',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'test@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password'),
                ],
                type: 'object',
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Bearer token issued.'),
            new OA\Response(response: 401, description: 'Invalid credentials.'),
        ],
    )]
    public function login(): void
    {
    }

    #[OA\Get(
        path: '/me',
        summary: 'Get the authenticated user',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Authenticated user.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
        ],
    )]
    public function me(): void
    {
    }

    #[OA\Post(
        path: '/logout',
        summary: 'Revoke the current bearer token',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Logged out.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
        ],
    )]
    public function logout(): void
    {
    }

    #[OA\Get(
        path: '/users',
        summary: 'List users',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\QueryParameter(name: 'search', required: false, schema: new OA\Schema(type: 'string'), example: 'john'),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer'), example: 15),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated user list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/User'),
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing users.view right.'),
        ],
    )]
    public function usersIndex(): void
    {
    }

    #[OA\Post(
        path: '/users',
        summary: 'Create a user',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreUserRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'User created.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing users.manage right.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function usersStore(): void
    {
    }

    #[OA\Get(
        path: '/users/{user}',
        summary: 'Show a user',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User details.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing users.view right.'),
            new OA\Response(response: 404, description: 'User not found.'),
        ],
    )]
    public function usersShow(): void
    {
    }

    #[OA\Patch(
        path: '/users/{user}',
        summary: 'Update a user',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateUserRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'User updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing users.manage right.'),
            new OA\Response(response: 404, description: 'User not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function usersUpdate(): void
    {
    }

    #[OA\Put(
        path: '/users/{user}',
        summary: 'Replace a user',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateUserRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'User updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing users.manage right.'),
            new OA\Response(response: 404, description: 'User not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function usersReplace(): void
    {
    }

    #[OA\Delete(
        path: '/users/{user}',
        summary: 'Delete a user',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'User deleted.'),
            new OA\Response(response: 403, description: 'Missing users.manage right.'),
            new OA\Response(response: 404, description: 'User not found.'),
            new OA\Response(response: 422, description: 'Cannot delete your own user account.'),
        ],
    )]
    public function usersDestroy(): void
    {
    }
}
