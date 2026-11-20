@extends('layouts.app')
@section('title', 'My children · GoTry')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/parent.css') }}">
@endpush
@section('content')
<div class="parent-ui children-page">
    <a class="children-back" href="{{ route('parent.dashboard') }}">← My List</a>
    <section class="parent-banner children-banner">
        <div>
            <h1>My children</h1>
            <p>Save their details once. Choose a child for each activity.</p>
        </div>
        <a class="button" href="#add-child">+ Add a child</a>
    </section>
    @if(session('status'))
        <p class="notice" role="status">{{ session('status') }}</p>
    @endif
    <div class="children-layout">
        <section aria-labelledby="children-heading">
            <h2 id="children-heading">Your children <span class="children-count">{{ $children->count() }}</span></h2>
            <div class="children-grid">
                @forelse($children as $child)
                    <article class="child-profile">
                        <span class="child-avatar" aria-hidden="true">{{ mb_substr($child->first_name, 0, 1) }}</span>
                        <div>
                            <h3>{{ $child->fullName() }}</h3>
                            <p>Age {{ $child->age }}</p>
                        </div>
                        @if($activity)
                            <a class="child-profile-action" href="{{ route('activities.show', ['activity' => $activity, 'child' => $child->id]) }}#trial">Select child →</a>
                        @else
                            <a class="child-profile-action" href="{{ route('activities.index') }}">Find an activity →</a>
                        @endif
                    </article>
                @empty
                    <div class="children-empty">
                        <span class="child-avatar" aria-hidden="true">+</span>
                        <h3>Add your first child</h3>
                        <p>Their profile will appear here, ready for your next activity.</p>
                    </div>
                @endforelse
            </div>
        </section>
        <section class="child-form-panel" id="add-child" aria-labelledby="add-child-heading">
            <h2 id="add-child-heading">Add a child</h2>
            @if($errors->any())
                <div class="error" role="alert">
                    <p>Please check the details below.</p>
                    <ul>
                        @foreach($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form method="post" action="{{ route('parent.children.store') }}">
                @csrf
                <input type="hidden" name="activity" value="{{ old('activity', $activity) }}">
                <div class="child-name-fields">
                    <div>
                        <label for="first_name">First name</label>
                        <input id="first_name" name="first_name" maxlength="39" value="{{ old('first_name') }}" required autocomplete="off" @if($errors->has('first_name')) aria-invalid="true" @endif>
                    </div>
                    <div>
                        <label for="surname">Surname</label>
                        <input id="surname" name="surname" maxlength="40" value="{{ old('surname') }}" required autocomplete="off" @if($errors->has('surname')) aria-invalid="true" @endif>
                    </div>
                </div>
                <label for="age">Age</label>
                <input id="age" name="age" type="number" min="1" max="18" value="{{ old('age') }}" required inputmode="numeric" @if($errors->has('age')) aria-invalid="true" @endif>
                <button type="submit">{{ $activity ? 'Save and return to activity' : 'Save child' }}</button>
            </form>
        </section>
    </div>
</div>
@endsection
