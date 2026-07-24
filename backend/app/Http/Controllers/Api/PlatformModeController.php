<?php

namespace App\Http\Controllers\Api;

use App\Services\BillingModeService;

class PlatformModeController extends ApiController
{
    public function __invoke(BillingModeService $billingMode)
    {
        return $this->ok($billingMode->payload());
    }
}
