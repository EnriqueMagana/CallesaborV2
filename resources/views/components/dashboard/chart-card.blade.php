@props(['id', 'title', 'description', 'label'])

<section class="dashboard-panel dashboard-chart-panel" aria-labelledby="{{ $id }}-title">
    <div class="dashboard-panel__header">
        <div>
            <span class="dashboard-panel__eyebrow">{{ $label }}</span>
            <h2 id="{{ $id }}-title">{{ $title }}</h2>
            <p>{{ $description }}</p>
        </div>
        {{ $actions ?? '' }}
    </div>
    <div id="{{ $id }}" class="dashboard-chart" data-dashboard-chart="{{ $id }}" aria-label="{{ $title }}"></div>
    <div class="dashboard-chart-fallback">
        {{ $fallback ?? '' }}
    </div>
</section>
