<?php

namespace App\Users\Http\Controllers\Api;

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
