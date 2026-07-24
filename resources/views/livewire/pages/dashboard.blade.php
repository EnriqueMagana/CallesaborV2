@php
    $user = auth()->user();
    $avatar = $user?->avatar ? Storage::url($user->avatar) : null;
    $initials = collect(explode(' ', trim($user?->name ?? 'U')))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->join('');
@endphp

<div
    class="dashboard-page {{ $dashboard['has_open_register'] ? '' : 'dashboard-page--resting' }}"
    data-dashboard-root
    data-dashboard-state="{{ $dashboard['has_open_register'] ? 'active' : 'resting' }}"
    wire:loading.class="is-loading"
>
    @if(! $dashboard['has_open_register'])
        @include('livewire.pages.dashboard.partials.rest-state')
    @else
        @include('livewire.pages.dashboard.partials.header')
        @include('livewire.pages.dashboard.partials.periods')
        @include('livewire.pages.dashboard.partials.stats')

        @if($dashboard['show_charts'])
            @include('livewire.pages.dashboard.partials.charts')
        @endif

        @if($dashboard['show_team_performance'])
            @include('livewire.pages.dashboard.partials.team-performance')
        @endif

        @include('livewire.pages.dashboard.partials.activity-actions')

        @if($dashboard['show_charts'])
            <script type="application/json" data-dashboard-data>@json($dashboard['chart_data'])</script>
        @endif

        @include('livewire.pages.dashboard.partials.loader')
    @endif
</div>
