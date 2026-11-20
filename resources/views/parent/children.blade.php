@extends('layouts.app')
@section('title', 'My children · GoTry')
@section('content')
<section class="panel">
    <a href="{{ route('parent.dashboard') }}">← My List</a>
    <h1>My children</h1>
    @if(session('status'))
        <p class="notice" role="status">{{ session('status') }}</p>
    @endif
    @if($errors->any())
        <p class="error" role="alert">{{ $errors->first() }}</p>
    @endif
    @forelse($children as $child)
        <p><strong>{{ $child->fullName() }}</strong> · Age {{ $child->age }}</p>
    @empty
        <p>Add your children here, then select a child when registering for an activity.</p>
    @endforelse
    <h2>Add a child</h2>
    <form method="post" action="{{ route('parent.children.store') }}">
        @csrf
        <input type="hidden" name="activity" value="{{ old('activity', $activity) }}">
        <label for="first_name">Child’s first name</label>
        <input id="first_name" name="first_name" maxlength="39" value="{{ old('first_name') }}" required>
        <label for="surname">Child’s surname</label>
        <input id="surname" name="surname" maxlength="40" value="{{ old('surname') }}" required>
        <label for="age">Age</label>
        <input id="age" name="age" type="number" min="1" max="18" value="{{ old('age') }}" required>
        <button type="submit">Add child</button>
    </form>
</section>
@endsection
