{{-- 419: the page (CSRF token) expired before a form was sent. --}}
@extends('errors.layout', ['code' => 419, 'icon' => 'clock', 'retry' => true])
