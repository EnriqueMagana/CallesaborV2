@props([
    'roles',
    'collapseAfter' => 2,
    'emptyLabel' => 'Sin rol',
])

@php
    $roleContext = app(\App\Services\UserRoleContext::class);
    $orderedRoles = $roleContext->ordered($roles);
    $shouldCollapse = $orderedRoles->count() > (int) $collapseAfter;
    $visibleRoles = $shouldCollapse ? $orderedRoles->take(1) : $orderedRoles;
    $remaining = $shouldCollapse ? $orderedRoles->count() - 1 : 0;
    $allLabels = $orderedRoles->map(fn ($role) => $roleContext->label($role->name));
    $primaryRole = $orderedRoles->first();
@endphp

<span
    {{ $attributes->class(['app-role-summary', 'is-empty' => $orderedRoles->isEmpty()]) }}
    @if($orderedRoles->isNotEmpty())
        aria-label="Roles asignados: {{ $allLabels->join(', ') }}"
        title="{{ $allLabels->join(', ') }}"
    @endif
>
    <i class="bx {{ $primaryRole ? $roleContext->roleIcon($primaryRole) : 'bx-shield-quarter' }}" aria-hidden="true"></i>
    @forelse($visibleRoles as $role)
        <span class="app-role-summary__label">{{ $roleContext->label($role->name) }}</span>
    @empty
        <span class="app-role-summary__label">{{ $emptyLabel }}</span>
    @endforelse
    @if($remaining > 0)
        <span class="app-role-summary__more" aria-hidden="true">+{{ $remaining }}</span>
    @endif
</span>
