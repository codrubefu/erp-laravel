<?php

namespace App\Subscription\Http\Controllers\Api;

use App\Subscription\Http\Requests\StoreSubscriptionRequest;
use App\Subscription\Http\Requests\UpdateSubscriptionRequest;
use App\Subscription\Http\Resources\SubscriptionResource;
use App\Subscription\Models\Subscription;
use App\Payments\Models\Payment;
use App\Subscription\Models\SubscriptionUser;
use App\Subscription\Services\SubscriptionLifecycleService;
use App\Users\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionLifecycleService $lifecycle)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $subscriptions = Subscription::query()
            ->withCount('users')
            ->when($request->boolean('with_trashed'), fn ($query) => $query->withTrashed())
            ->when($request->boolean('only_trashed'), fn ($query) => $query->onlyTrashed())
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return SubscriptionResource::collection($subscriptions);
    }

    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $userIds = $data['user_ids'] ?? [];
        unset($data['user_ids']);

        $subscription = DB::transaction(function () use ($data, $userIds): Subscription {
            $subscription = Subscription::query()->create($data);
            $subscription->users()->sync($userIds);

            return $subscription;
        });

        return (new SubscriptionResource($subscription->load('users')->loadCount('users')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Subscription $subscription): SubscriptionResource
    {
        return new SubscriptionResource($subscription->load('users')->loadCount('users'));
    }

    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): SubscriptionResource
    {
        $data = $request->validated();
        $userIds = $data['user_ids'] ?? null;
        unset($data['user_ids']);

        DB::transaction(function () use ($subscription, $data, $userIds): void {
            $subscription->update($data);

            if ($userIds !== null) {
                $currentUserIds = $subscription->users()->pluck('users.id')->all();
                $subscription->users()->detach(array_diff($currentUserIds, $userIds));
                $subscription->users()->syncWithoutDetaching($userIds);
            }
        });

        return new SubscriptionResource($subscription->load('users')->loadCount('users'));
    }

    public function destroy(Subscription $subscription): JsonResponse
    {
        $subscription->delete();

        return response()->json(status: 204);
    }

    public function restore(int $subscription): SubscriptionResource
    {
        $subscription = Subscription::onlyTrashed()->findOrFail($subscription);
        $subscription->restore();

        return new SubscriptionResource($subscription->load('users')->loadCount('users'));
    }

    public function toggleActive(Subscription $subscription): SubscriptionResource
    {
        $subscription->update([
            'is_active' => ! $subscription->is_active,
        ]);

        return new SubscriptionResource($subscription->load('users')->loadCount('users'));
    }

    public function activate(Request $request, SubscriptionUser $assignment): JsonResponse
    {
        $data = $request->validate(['payment_id' => ['nullable', 'integer', 'exists:payments,id']]);
        $payment = isset($data['payment_id']) ? Payment::query()->findOrFail($data['payment_id']) : null;
        $assignment = $this->lifecycle->activate($assignment, $payment);

        return response()->json(['data' => $assignment->load(['subscription', 'user', 'activationPayment'])]);
    }

    public function suspend(Request $request, SubscriptionUser $assignment): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'resume_at' => ['nullable', 'date', 'after:now'],
        ]);
        $assignment = $this->lifecycle->suspend(
            $assignment,
            $data['reason'],
            isset($data['resume_at']) ? now()->parse($data['resume_at']) : null,
        );

        return response()->json(['data' => $assignment]);
    }

    public function resume(SubscriptionUser $assignment): JsonResponse
    {
        return response()->json(['data' => $this->lifecycle->resume($assignment)]);
    }

    public function consume(SubscriptionUser $assignment): JsonResponse
    {
        return response()->json(['data' => $this->lifecycle->consumeAccess($assignment)]);
    }
}
