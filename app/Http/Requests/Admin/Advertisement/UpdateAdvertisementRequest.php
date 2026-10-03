<?php

namespace App\Http\Requests\Admin\Advertisement;

/**
 * The media file is optional on edit: leaving it empty keeps the current one.
 */
class UpdateAdvertisementRequest extends SaveAdvertisementRequest
{
    protected function mediaIsRequired(): bool
    {
        return false;
    }
}
