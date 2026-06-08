<?php

namespace App\Users\Http\Controllers\Api;

use App\CustomFields\Http\Resources\CustomFieldValueResource;
use App\CustomFields\Services\CustomFieldDefinitionService;
use App\CustomFields\Services\CustomFieldValueService;
use App\Events\Http\Resources\EventOccurrenceResource;
use App\Subscription\Http\Resources\SubscriptionResource;
use App\Users\Http\Controllers\Controller;
use App\Users\Http\Requests\UpdateMePasswordRequest;
use App\Users\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MeController extends Controller
{
    private const CUSTOM_FIELD_ENTITY_TYPE = 'users';

    public function __construct(
        private readonly CustomFieldDefinitionService $customFieldDefinitions,
        private readonly CustomFieldValueService $customFieldValues,
    ) {
    }

    public function show(Request $request): UserResource
    {
        return new UserResource(
            $request->user()->load(['groups.rights', 'locations', 'activeSubscriptions'])
        );
    }

    public function updatePassword(UpdateMePasswordRequest $request): JsonResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully.',
            'data' => null,
        ]);
    }

    public function customFields(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $organizationId = (int) $user->organization_id;
        $fields = $this->customFieldDefinitions->forEntityType($organizationId, self::CUSTOM_FIELD_ENTITY_TYPE);
        $values = $this->customFieldValues
            ->valuesForEntity($organizationId, self::CUSTOM_FIELD_ENTITY_TYPE, (int) $user->id)
            ->keyBy('custom_field_id');

        return CustomFieldValueResource::collection(
            $fields->map(fn ($field) => [
                'field' => $field,
                'value' => $values->get($field->id),
            ])->values()
        );
    }

    public function events(Request $request): AnonymousResourceCollection
    {
        $occurrences = $request->user()
            ->eventOccurrences()
            ->with('event')
            ->orderByDesc('start_datetime')
            ->paginate($request->integer('per_page', 15));

        return EventOccurrenceResource::collection($occurrences);
    }

    public function subscriptions(Request $request): AnonymousResourceCollection
    {
        $subscriptions = $request->user()
            ->subscriptions()
            ->orderByDesc('subscription_user.created_at')
            ->paginate($request->integer('per_page', 15));

        return SubscriptionResource::collection($subscriptions);
    }
}
