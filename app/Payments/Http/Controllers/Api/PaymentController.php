<?php

namespace App\Payments\Http\Controllers\Api;

use App\Payments\Http\Requests\AttachPaymentModelRequest;
use App\Payments\Http\Requests\CancelPaymentRequest;
use App\Payments\Http\Requests\StorePaymentRequest;
use App\Payments\Http\Resources\PaymentResource;
use App\Payments\Models\Payment;
use App\Payments\Services\PaymentService;
use App\Payments\Services\ReceiptService;
use App\Users\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments, private readonly ReceiptService $receipts)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $payments = Payment::query()
            ->with(['admin'])
            ->where('organization_id', $request->user()->organization_id)
            ->latest('paid_at')
            ->paginate(min(max($request->integer('per_page', 15), 1), 200));

        return PaymentResource::collection($payments);
    }

    public function callback(Request $request): JsonResponse
    {
        $secret = (string) config('services.payments.callback_secret');
        $signature = (string) $request->header('X-Payment-Signature');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        if ($secret === '' || ! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid callback signature.'], 401);
        }

        $data = Validator::make($request->json()->all(), [
            'external_reference' => ['required', 'string'],
            'transaction_id' => ['sometimes', 'string', 'max:255'],
            'status' => ['required', Rule::in([Payment::STATUS_PENDING, Payment::STATUS_CONFIRMED, Payment::STATUS_FAILED, Payment::STATUS_REFUNDED, Payment::STATUS_CANCELLED])],
            'failure_reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ])->validate();

        return response()->json(['data' => new PaymentResource($this->payments->processCallback($data))]);
    }

    public function receipt(Request $request, Payment $payment): Response
    {
        abort_unless((int) $payment->organization_id === (int) $request->user()->organization_id, 404);
        abort_unless(
            in_array($payment->status, Payment::RECEIPT_STATUSES, true) && $payment->receipt_number !== null,
            409,
            'Receipt is only available for confirmed or cancelled payments.',
        );

        return $this->receipts->download($payment);
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $payment = $this->payments->create($request->validated(), $request->user());

        return (new PaymentResource($payment->load(['admin'])))
            ->response()
            ->setStatusCode(201);
    }

    public function cancel(CancelPaymentRequest $request, Payment $payment): PaymentResource
    {
        abort_unless((int) $payment->organization_id === (int) $request->user()->organization_id, 404);

        $payment = $this->payments->cancel($payment, $request->validated('reason'), $request->user());

        return new PaymentResource($payment->load(['admin']));
    }

    public function attachModel(AttachPaymentModelRequest $request, Payment $payment): PaymentResource
    {
        abort_unless((int) $payment->organization_id === (int) $request->user()->organization_id, 404);
        $data = $request->validated();

        $payment = $this->payments->attachModel(
            $payment,
            $data['model_type'],
            $data['model_id'],
        );

        return new PaymentResource($payment->load(['admin']));
    }
}
