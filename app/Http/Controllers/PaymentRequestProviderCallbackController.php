<?php

declare(strict_types=1);

namespace Modules\ERP\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\ERP\Models\PaymentRequest;
use Modules\ERP\Services\Payments\PaymentRequestService;
use Modules\ERP\Support\ErpConnectionContext;
use Symfony\Component\HttpFoundation\Response;

final class PaymentRequestProviderCallbackController extends Controller
{
    public function __construct(
        private readonly PaymentRequestService $service,
        private readonly ErpConnectionContext $connection_context,
    ) {}

    public function __invoke(Request $request, string $provider): JsonResponse
    {
        $key = config("erp.payment_requests.providers.{$provider}.callback_api_key");
        abort_unless(is_string($key) && $key !== '', Response::HTTP_SERVICE_UNAVAILABLE);
        $authorization = $request->bearerToken() ?? '';
        abort_unless(hash_equals($key, $authorization), Response::HTTP_UNAUTHORIZED);
        $source = $this->connection_context->model(PaymentRequest::class);
        $payment_request = $this->service->applyCallback($provider, $request->all(), $source);

        return response()->json(['data' => ['id' => $payment_request->id, 'status' => $payment_request->status->value]]);
    }
}
