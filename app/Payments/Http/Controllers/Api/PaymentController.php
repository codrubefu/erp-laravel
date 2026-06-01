<?php

namespace App\Payments\Http\Controllers\Api;

use App\Payments\Http\Requests\AttachPaymentModelRequest;
use App\Payments\Http\Requests\StorePaymentRequest;
use App\Payments\Http\Resources\PaymentResource;
use App\Payments\Models\Payment;
use App\Payments\Services\PaymentService;
use App\Users\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $payments = Payment::query()
            ->with(['admin', 'subscription'])
            ->latest('paid_at')
            ->paginate($request->integer('per_page', 15));

        return PaymentResource::collection($payments);
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $payment = $this->payments->create($request->validated(), $request->user());

        return (new PaymentResource($payment->load(['admin', 'subscription'])))
            ->response()
            ->setStatusCode(201);
    }

    public function attachModel(AttachPaymentModelRequest $request, Payment $payment): PaymentResource
    {
        $data = $request->validated();

        $payment = $this->payments->attachModel(
            $payment,
            $data['model_type'],
            $data['model_id'],
        );

        return new PaymentResource($payment->load(['admin', 'subscription']));
    }
}
