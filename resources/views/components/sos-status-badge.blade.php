@props(['sosRequest'])

@php
    use App\Models\SosRequest;

    // A request whose deadline passed keeps `open` in the database — only an
    // explicit decision writes that column — so "expired" is derived here.
    [$color, $label] = match (true) {
        $sosRequest->status === SosRequest::STATUS_FILLED => ['emerald', __('Preenchido')],
        $sosRequest->status === SosRequest::STATUS_CANCELLED => ['gray', __('Cancelado')],
        ! $sosRequest->isOpen() => ['gray', __('Expirado')],
        default => ['red', __('Aberto')],
    };
@endphp

<x-badge :color="$color" {{ $attributes }}>{{ $label }}</x-badge>
