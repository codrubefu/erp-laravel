<?php

namespace App\Events\Http\Controllers\Api;

use App\Events\Http\Requests\AddEventParticipantRequest;
use App\Events\Http\Resources\EventParticipantResource;
use App\Events\Models\EventOccurrence;
use App\Events\Services\EventEligibilityService;
use App\Users\Http\Controllers\Controller;
use App\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class EventParticipantController extends Controller
{
    public function __construct(private readonly EventEligibilityService $eligibility)
    {
    }

    #[OA\Get(
        path: '/event-occurrences/{occurrence}/participants',
        summary: 'List occurrence participants',
        description: 'Returns paginated users registered for a concrete event occurrence.',
        security: [['bearerAuth' => []]],
        tags: ['Event Participants'],
        parameters: [
            new OA\PathParameter(name: 'occurrence', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'page', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'sort', required: false, schema: new OA\Schema(type: 'string', example: 'last_name')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Participants list.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/EventParticipant'))])),
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/StandardSuccessResponse')),
            new OA\Response(response: 400, description: 'Bad request.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function index(EventOccurrence $occurrence): AnonymousResourceCollection
    {
        return EventParticipantResource::collection(
            $occurrence->participants()->orderBy('last_name')->orderBy('first_name')->paginate(request()->integer('per_page', 15))
        );
    }

    #[OA\Post(
        path: '/event-occurrences/{occurrence}/participants',
        summary: 'Add occurrence participant',
        description: 'Adds a user to an occurrence after duplicate, capacity, and active-subscription eligibility checks.',
        security: [['bearerAuth' => []]],
        tags: ['Event Participants'],
        parameters: [new OA\PathParameter(name: 'occurrence', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/AddEventParticipantRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Success.', content: new OA\JsonContent(ref: '#/components/schemas/StandardSuccessResponse')),
            new OA\Response(response: 201, description: 'Participant added.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/EventParticipant')])),
            new OA\Response(response: 400, description: 'Bad request.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function store(AddEventParticipantRequest $request, EventOccurrence $occurrence): JsonResponse
    {
        $data = $request->validated();
        $user = User::query()->findOrFail($data['user_id']);
        $occurrence->load('event.requiredSubscription');

        if ($occurrence->participants()->whereKey($user->id)->exists()) {
            return $this->error('User is already registered for this occurrence.', 422);
        }

        if (! $this->eligibility->canUserJoinOccurrence($user, $occurrence)) {
            return $this->error('User does not have the required active subscription.', 403);
        }

        $maxParticipants = $occurrence->event->max_participants;
        if ($maxParticipants !== null && $occurrence->activeParticipants()->count() >= $maxParticipants) {
            return $this->error('Event occurrence has reached the maximum number of participants.', 400);
        }

        DB::transaction(function () use ($occurrence, $user, $data): void {
            $occurrence->participants()->attach($user->id, [
                'status' => $data['status'] ?? 'registered',
                'registered_at' => $data['registered_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);
        });

        $participant = $occurrence->participants()->whereKey($user->id)->firstOrFail();

        return response()->json([
            'success' => true,
            'message' => 'Participant added successfully.',
            'data' => new EventParticipantResource($participant),
        ], 201);
    }

    #[OA\Delete(
        path: '/event-occurrences/{occurrence}/participants/{user}',
        summary: 'Remove occurrence participant',
        description: 'Removes a user from the participant list for a concrete event occurrence.',
        security: [['bearerAuth' => []]],
        tags: ['Event Participants'],
        parameters: [
            new OA\PathParameter(name: 'occurrence', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Participant removed.', content: new OA\JsonContent(ref: '#/components/schemas/StandardSuccessResponse')),
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/StandardSuccessResponse')),
            new OA\Response(response: 400, description: 'Bad request.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function destroy(EventOccurrence $occurrence, User $user): JsonResponse
    {
        if (! $occurrence->participants()->whereKey($user->id)->exists()) {
            return $this->error('Participant not found for this occurrence.', 404);
        }

        $occurrence->participants()->detach($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Participant removed successfully.',
            'data' => null,
        ]);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => [],
        ], $status);
    }
}
