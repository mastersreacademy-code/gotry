@if($sessions !== [])
<section class="review-section">
    <h2>Group trial sessions</h2>
    <ul>
    @foreach($sessions as $session)
        <li>{{ $session['start'] }} (Sydney) · {{ \App\Models\Activity::STATUSES[$session['status']] }} · {{ $session['count'] }} active interests</li>
    @endforeach
    </ul>
</section>
@endif
