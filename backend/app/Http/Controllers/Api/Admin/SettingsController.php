<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\PlatformSettingsService;

class SettingsController extends ApiController
{
    public function __construct(private PlatformSettingsService $settings) {}

    public function index()
    {
        return $this->ok($this->settings->all());
    }

    public function update(UpdateSettingsRequest $request)
    {
        $updated = $this->settings->update($request->validated()['settings'], $request->user());

        return $this->ok($updated, 'Settings updated');
    }
}
