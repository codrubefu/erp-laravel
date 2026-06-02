<?php

namespace App\Payments\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'PaymentAdmin',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 34),
        new OA\Property(property: 'user_code', type: 'string', nullable: true, example: 'USR00000000000000000000000000034'),
        new OA\Property(property: 'first_name', type: 'string', example: 'Test'),
        new OA\Property(property: 'last_name', type: 'string', example: 'User'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+15550000000'),
        new OA\Property(property: 'active', type: 'boolean', example: true),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'test@example.com'),
        new OA\Property(property: 'email_verified_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Payment',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 8),
        new OA\Property(property: 'first_name', type: 'string', example: 'Brianne'),
        new OA\Property(property: 'last_name', type: 'string', example: 'Ankunding'),
        new OA\Property(property: 'payment_type_id', type: 'integer', example: 1),
        new OA\Property(property: 'payment_type', type: 'string', example: 'cash'),
        new OA\Property(property: 'model_type', type: 'string', example: 'subscription_user', default: 'subscription_user'),
        new OA\Property(property: 'model_id', type: 'integer', nullable: true, example: 30),
        new OA\Property(property: 'amount', type: 'string', example: '200.00'),
        new OA\Property(property: 'paid_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'admin_id', type: 'integer', example: 34),
        new OA\Property(property: 'admin', ref: '#/components/schemas/PaymentAdmin'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StorePaymentRequest',
    required: ['first_name', 'last_name', 'payment_type_id', 'model_id', 'amount', 'paid_at'],
    properties: [
        new OA\Property(property: 'first_name', type: 'string', example: 'Brianne'),
        new OA\Property(property: 'last_name', type: 'string', example: 'Ankunding'),
        new OA\Property(property: 'payment_type_id', type: 'integer', example: 1),
        new OA\Property(property: 'model_type', type: 'string', example: 'subscription_user', default: 'subscription_user'),
        new OA\Property(property: 'model_id', type: 'integer', example: 30, description: 'Plain model identifier. It is not a foreign key relation.'),
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 200),
        new OA\Property(property: 'paid_at', type: 'string', format: 'date-time', example: '2026-06-02 21:04:00'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'AttachPaymentModelRequest',
    required: ['model_type', 'model_id'],
    properties: [
        new OA\Property(property: 'model_type', type: 'string', example: 'subscription_user', default: 'subscription_user'),
        new OA\Property(property: 'model_id', type: 'integer', example: 30, description: 'Plain model identifier. It is not a foreign key relation.'),
    ],
    type: 'object',
)]
class Schemas
{
}