{{-- 404: an address that matches nothing, or a record that does not exist. --}}
@extends('errors.layout', ['code' => 404, 'icon' => 'search', 'search' => true])
