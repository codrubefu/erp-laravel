<?php

namespace App\Users\OpenApi;

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
        path: '/administrators',
        summary: 'List users assigned to at least one group',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\QueryParameter(name: 'search', required: false, schema: new OA\Schema(type: 'string'), example: 'john'),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer'), example: 15),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated administrator user list.',
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
    public function administratorsIndex(): void
    {
    }

    #[OA\Get(
        path: '/clients',
        summary: 'List users not assigned to any group',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\QueryParameter(name: 'search', required: false, schema: new OA\Schema(type: 'string'), example: 'john'),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer'), example: 15),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated client user list.',
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
    public function clientsIndex(): void
    {
    }

    #[OA\Get(
        path: '/groups',
        summary: 'List user groups',
        security: [['bearerAuth' => []]],
        tags: ['Groups'],
        parameters: [
            new OA\QueryParameter(name: 'search', required: false, schema: new OA\Schema(type: 'string'), example: 'admin'),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer'), example: 15),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated group list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Group'),
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing groups.view right.'),
        ],
    )]
    public function groupsIndex(): void
    {
    }

    #[OA\Post(
        path: '/groups',
        summary: 'Create a user group',
        security: [['bearerAuth' => []]],
        tags: ['Groups'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreGroupRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Group created.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Group'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing groups.manage right.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function groupsStore(): void
    {
    }

    #[OA\Get(
        path: '/groups/{group}',
        summary: 'Show a user group',
        security: [['bearerAuth' => []]],
        tags: ['Groups'],
        parameters: [
            new OA\PathParameter(name: 'group', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Group details.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Group'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing groups.view right.'),
            new OA\Response(response: 404, description: 'Group not found.'),
        ],
    )]
    public function groupsShow(): void
    {
    }

    #[OA\Patch(
        path: '/groups/{group}',
        summary: 'Update a user group',
        security: [['bearerAuth' => []]],
        tags: ['Groups'],
        parameters: [
            new OA\PathParameter(name: 'group', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateGroupRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Group updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Group'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing groups.manage right.'),
            new OA\Response(response: 404, description: 'Group not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function groupsUpdate(): void
    {
    }

    #[OA\Put(
        path: '/groups/{group}',
        summary: 'Replace a user group',
        security: [['bearerAuth' => []]],
        tags: ['Groups'],
        parameters: [
            new OA\PathParameter(name: 'group', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateGroupRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Group updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Group'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing groups.manage right.'),
            new OA\Response(response: 404, description: 'Group not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function groupsReplace(): void
    {
    }

    #[OA\Delete(
        path: '/groups/{group}',
        summary: 'Delete a user group',
        security: [['bearerAuth' => []]],
        tags: ['Groups'],
        parameters: [
            new OA\PathParameter(name: 'group', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Group deleted.'),
            new OA\Response(response: 403, description: 'Missing groups.manage right.'),
            new OA\Response(response: 404, description: 'Group not found.'),
            new OA\Response(response: 422, description: 'Cannot delete a group that still has users.'),
        ],
    )]
    public function groupsDestroy(): void
    {
    }

    #[OA\Get(
        path: '/rights',
        summary: 'List rights',
        security: [['bearerAuth' => []]],
        tags: ['Rights'],
        parameters: [
            new OA\QueryParameter(name: 'search', required: false, schema: new OA\Schema(type: 'string'), example: 'users'),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer'), example: 15),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated right list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Right'),
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing rights.view right.'),
        ],
    )]
    public function rightsIndex(): void
    {
    }

    #[OA\Post(
        path: '/rights',
        summary: 'Create a right',
        security: [['bearerAuth' => []]],
        tags: ['Rights'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreRightRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Right created.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Right'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing rights.manage right.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function rightsStore(): void
    {
    }

    #[OA\Get(
        path: '/rights/{right}',
        summary: 'Show a right',
        security: [['bearerAuth' => []]],
        tags: ['Rights'],
        parameters: [
            new OA\PathParameter(name: 'right', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Right details.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Right'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing rights.view right.'),
            new OA\Response(response: 404, description: 'Right not found.'),
        ],
    )]
    public function rightsShow(): void
    {
    }

    #[OA\Patch(
        path: '/rights/{right}',
        summary: 'Update a right',
        security: [['bearerAuth' => []]],
        tags: ['Rights'],
        parameters: [
            new OA\PathParameter(name: 'right', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateRightRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Right updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Right'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing rights.manage right.'),
            new OA\Response(response: 404, description: 'Right not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function rightsUpdate(): void
    {
    }

    #[OA\Put(
        path: '/rights/{right}',
        summary: 'Replace a right',
        security: [['bearerAuth' => []]],
        tags: ['Rights'],
        parameters: [
            new OA\PathParameter(name: 'right', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateRightRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Right updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Right'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing rights.manage right.'),
            new OA\Response(response: 404, description: 'Right not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function rightsReplace(): void
    {
    }

    #[OA\Delete(
        path: '/rights/{right}',
        summary: 'Delete a right',
        security: [['bearerAuth' => []]],
        tags: ['Rights'],
        parameters: [
            new OA\PathParameter(name: 'right', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Right deleted.'),
            new OA\Response(response: 403, description: 'Missing rights.manage right.'),
            new OA\Response(response: 404, description: 'Right not found.'),
            new OA\Response(response: 422, description: 'Cannot delete a right assigned to groups.'),
        ],
    )]
    public function rightsDestroy(): void
    {
    }

    #[OA\Get(
        path: '/locations',
        summary: 'List locations',
        security: [['bearerAuth' => []]],
        tags: ['Locations'],
        parameters: [
            new OA\QueryParameter(name: 'search', required: false, schema: new OA\Schema(type: 'string'), example: 'office'),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer'), example: 15),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated location list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Location'),
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing locations.view right.'),
        ],
    )]
    public function locationsIndex(): void
    {
    }

    #[OA\Post(
        path: '/locations',
        summary: 'Create a location',
        security: [['bearerAuth' => []]],
        tags: ['Locations'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreLocationRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Location created.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Location'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing locations.manage right.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function locationsStore(): void
    {
    }

    #[OA\Get(
        path: '/locations/{location}',
        summary: 'Show a location',
        security: [['bearerAuth' => []]],
        tags: ['Locations'],
        parameters: [
            new OA\PathParameter(name: 'location', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Location details.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Location'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing locations.view right.'),
            new OA\Response(response: 404, description: 'Location not found.'),
        ],
    )]
    public function locationsShow(): void
    {
    }

    #[OA\Patch(
        path: '/locations/{location}',
        summary: 'Update a location',
        security: [['bearerAuth' => []]],
        tags: ['Locations'],
        parameters: [
            new OA\PathParameter(name: 'location', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateLocationRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Location updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Location'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing locations.manage right.'),
            new OA\Response(response: 404, description: 'Location not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function locationsUpdate(): void
    {
    }

    #[OA\Put(
        path: '/locations/{location}',
        summary: 'Replace a location',
        security: [['bearerAuth' => []]],
        tags: ['Locations'],
        parameters: [
            new OA\PathParameter(name: 'location', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateLocationRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Location updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Location'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing locations.manage right.'),
            new OA\Response(response: 404, description: 'Location not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function locationsReplace(): void
    {
    }

    #[OA\Delete(
        path: '/locations/{location}',
        summary: 'Delete a location',
        security: [['bearerAuth' => []]],
        tags: ['Locations'],
        parameters: [
            new OA\PathParameter(name: 'location', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Location deleted.'),
            new OA\Response(response: 403, description: 'Missing locations.manage right.'),
            new OA\Response(response: 404, description: 'Location not found.'),
        ],
    )]
    public function locationsDestroy(): void
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

    #[OA\Post(
        path: '/users/subscription/{user}',
        summary: 'Sync subscriptions for a user',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SyncUserSubscriptionsRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'User subscriptions synced.',
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
    public function usersSyncSubscriptions(): void
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
