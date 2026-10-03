<?php

namespace App\Http\Requests\Admin\Advertisement;

class StoreAdvertisementRequest extends SaveAdvertisementRequest
{
    protected function mediaIsRequired(): bool
    {
        return true;
    }
}
