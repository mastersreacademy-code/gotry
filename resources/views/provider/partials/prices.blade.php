<section class="price-section"><h2>Parent price preview</h2>
    <div class="grid">@foreach($prices as $price)<article><h3>{{ $price['label'] }}</h3>
        <dl class="review-list">
            <div><dt>Regular price</dt><dd>${{ number_format($price['regular'] / 100, 2) }}</dd></div>
            <div><dt>GoTry discount</dt><dd>−${{ number_format($price['discount'] / 100, 2) }}</dd></div>
            <div><dt>{{ $price['period'] }} price</dt><dd><strong>${{ number_format($price['total'] / 100, 2) }}</strong></dd></div>
        </dl>
    </article>@endforeach</div>
    <p class="text-small">Trial reservation fees are separate from regular programme fees. No payment is collected on this page.</p>
</section>
