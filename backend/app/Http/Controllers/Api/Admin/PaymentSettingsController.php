<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\UpdatePaymentSettingsRequest;
use App\Services\PaymentSettingsService;
use Illuminate\Http\Request;

class PaymentSettingsController extends ApiController
{
    public function __construct(private PaymentSettingsService $settings) {}

    public function index(Request $request)
    {
        return $this->ok($this->settings->adminPayload($this->webhookUrl($request)));
    }

    public function update(UpdatePaymentSettingsRequest $request)
    {
        return $this->ok(
            $this->settings->update($request->validated(), $request->user(), $this->webhookUrl($request)),
            'Payment settings updated'
        );
    }

    public function test(Request $request)
    {
        return $this->ok($this->settings->testConnection($request->user()), 'Stripe connection succeeded');
    }

    private function webhookUrl(Request $request): string
    {
        return rtrim($request->getSchemeAndHttpHost(), '/').'/api/webhooks/stripe';
    }
}
