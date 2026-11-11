@extends('layouts.app')
@section('title', $steps[$step].' · Provider registration · GoTry')
@section('content')
<section class="panel wizard">
    <div class="heading-row"><strong>Provider Registration</strong><span>Step {{ $step }} of 5 · {{ $step * 20 }}%</span></div>
    <progress value="{{ $step }}" max="5" aria-label="Registration progress">{{ $step * 20 }}%</progress>
    <ol class="wizard-steps">@foreach($steps as $number => $label)<li @if($number === $step) aria-current="step" @endif>{{ $number }}. {{ $label }}</li>@endforeach</ol>
    <h1>{{ [1 => 'Tell us about your organisation', 2 => 'What activity are you offering?', 3 => 'Set up your introductory trial', 4 => 'Regular lesson details', 5 => 'Review your listing'][$step] }}</h1>
    <p>{{ [1 => 'These details help us verify your business and contact you about bookings.', 2 => 'Describe what children will learn, do and experience.', 3 => 'These details apply only to the introductory trial, separate from your regular paid programme.', 4 => 'Provide details of ongoing lessons families may choose after their introductory trial.', 5 => 'Check trial and regular programme details separately before submitting.'][$step] }}</p>
    @if($errors->any())<div class="error" role="alert"><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
    <form method="post" action="{{ $step === 5 ? route('provider.submit') : route('provider.save', ['step' => $step]) }}" id="activity-form">
        @csrf
        @if($step < 5)
            <div class="fields">
            @foreach($fields as $field)
                @php([$name, $label, $type] = $field)
                @php($options = $field[3] ?? [])
                @php($condition = $field[4] ?? null)
                <div class="field {{ $type === 'textarea' ? 'wide' : '' }}" @if($condition) data-when-field="{{ $condition[0] }}" data-when-value="{{ $condition[1] }}" @endif data-field="{{ $name }}">
                    <label for="{{ $name }}">{{ $label }}</label>
                    @if($type === 'select')
                        <select id="{{ $name }}" name="{{ $name }}" @error($name) aria-invalid="true" aria-describedby="error-{{ $name }}" @enderror>
                            @unless(in_array($name, ['trial_type','regular_format','multiple_days','discount_type','discount_applies']))<option value="">Select…</option>@endunless
                            @foreach($options as $value => $text)<option value="{{ $value }}" @selected((string) old($name, $data[$name] ?? '') === (string) $value)>{{ $text }}</option>@endforeach
                        </select>
                    @elseif($type === 'textarea')
                        <textarea id="{{ $name }}" name="{{ $name }}" rows="4" @error($name) aria-invalid="true" aria-describedby="error-{{ $name }}" @enderror>{{ old($name, $data[$name] ?? '') }}</textarea>
                    @else
                        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $data[$name] ?? '') }}" @if($type === 'number') min="0" step="{{ in_array($name, ['standard_price','small_group_price','individual_price','discount_value']) ? '0.01' : '1' }}" @endif @error($name) aria-invalid="true" aria-describedby="error-{{ $name }}" @enderror>
                    @endif
                    @error($name)<span id="error-{{ $name }}" class="field-error">{{ $message }}</span>@enderror
                </div>
            @endforeach
            </div>
            @if($step === 3)<p class="notice"><strong>Trial lesson fee: Free.</strong> You provide the introductory lesson at no charge. Group trial sessions share the duration, registration deadline and per-session group limits. Any GoTry reservation fee is separate and is only requested when the trial can proceed. One introductory trial per child, activity and provider.</p>@endif
            @if($step === 4)<section id="price-preview" aria-label="Parent price preview" aria-live="polite"></section><p>Discounts apply once to the first term or first lesson, as selected. Trial reservation fees are separate.</p>@endif
        @else
            @include('provider.partials.review')
            @include('provider.partials.prices')
            <input type="hidden" name="submission_token" value="{{ $token }}">
            <fieldset class="declarations"><legend>Provider declarations</legend>
                <label><input type="checkbox" name="child_safety" value="1" @checked(old('child_safety')) required> I confirm applicable child-safety and supervision requirements are satisfied.</label>
                <label><input type="checkbox" name="insurance" value="1" @checked(old('insurance')) required> I confirm appropriate insurance and venue permissions are in place, where required.</label>
                <label><input type="checkbox" name="honour_terms" value="1" @checked(old('honour_terms')) required> I agree to honour the advertised trial arrangements, group sizes, programme prices and GoTry discount.</label>
            </fieldset>
            <p>Submissions are reviewed before publication. Status after submission: Pending Approval.</p>
        @endif
        <div class="actions">
            <a class="button secondary" href="{{ $step > 1 ? route('provider.create', ['step' => $step - 1]) : route('provider.dashboard') }}">← {{ $step > 1 ? 'Back' : 'Dashboard' }}</a>
            <button type="submit">{{ $step === 5 ? 'Submit for approval' : 'Continue' }} →</button>
        </div>
    </form>
    <p class="text-small">Draft saved when you select Continue. Drafts belong to your signed-in session; signing out discards an unfinished draft.</p>
</section>
<script type="module" src="{{ asset('js/provider-wizard.mjs') }}"></script>
@endsection
