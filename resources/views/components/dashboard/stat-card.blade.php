@props(['stat'])

<article class="dashboard-stat dashboard-stat--{{ $stat['tone'] }}">
    <span class="dashboard-stat__icon" aria-hidden="true"><i class="bx {{ $stat['icon'] }}"></i></span>
    <div class="dashboard-stat__content">
        <p>{{ $stat['label'] }}</p>
        <strong>{{ $stat['value'] }}</strong>
        <small>{{ $stat['help'] }}</small>
    </div>
</article>
