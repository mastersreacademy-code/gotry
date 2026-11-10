@foreach($review as $section => $rows)
<section class="review-section"><h2>{{ $section }}</h2><dl class="review-list">@foreach($rows as $label => $value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl></section>
@endforeach
