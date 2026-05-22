<!DOCTYPE html>
<html>
<head>
    <title>@lang('app.title')</title>
</head>
<body>
    <h1>{{ __('Welcome back') }}</h1>

    <p>{{ trans('pagination.next') }}</p>
    <p>{{ trans_choice('messages.apples', $count) }}</p>

    @lang('Hello, world')

    {{-- A comment with __('not extracted because Blade strips comments before render, but we extract anyway') --}}

    <p>{{ __('Escaped: it\'s working') }}</p>

    {{ __("Double-quoted text") }}

    {{ Lang::get('validation.required') }}

    @php
        $message = __('Inline PHP block');
    @endphp

    {{-- Dynamic key, intentionally ignored --}}
    {{ __($someVariable) }}

    {{-- Multi-line call --}}
    {{ trans(
        'auth.failed'
    ) }}
</body>
</html>
