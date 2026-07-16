<?php

declare(strict_types=1);

namespace Modules\ERP\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\ERP\Services\EInvoice\EInvoiceSubmissionService;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class EInvoiceProviderCallbackController extends Controller
{
    public function __construct(
        private readonly EInvoiceSubmissionService $submission_service,
    ) {}

    public function __invoke(Request $request, string $provider): JsonResponse
    {
        $this->authorizeProviderCallback($request, $provider);

        try {
            $submission = $this->submission_service->applyProviderCallback($provider, $request->all());
        } catch (Throwable $exception) {
            Log::warning('ERP e-invoice provider callback could not be applied.', [
                'provider' => $provider,
                'payload' => $request->all(),
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return response()->json([
            'data' => [
                'id' => $submission->id,
                'status' => $submission->status->value,
            ],
        ]);
    }

    private function authorizeProviderCallback(Request $request, string $provider): void
    {
        $api_key = config('erp.einvoice.' . $provider . '.callback_api_key');

        if (! is_string($api_key) || mb_trim($api_key) === '') {
            return;
        }

        $authorization = $request->header('Authorization', '');
        $bearer = str_starts_with($authorization, 'Bearer ')
            ? mb_substr($authorization, 7)
            : $authorization;

        abort_unless(hash_equals($api_key, $bearer), Response::HTTP_UNAUTHORIZED);
    }
}
