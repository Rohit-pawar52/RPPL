{{-- 429: too many requests (throttled). --}}
@extends('errors.layout', ['code' => 429, 'icon' => 'bolt', 'retry' => true])
