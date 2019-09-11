@extends('layouts.page_bulk')

@section('title', __('Accesso super amministratori'))

@section('content')
<form method="post" action="{{ route('admin.login') }}" class="needs-validation" novalidate>
    @csrf
    <div class="mt-5">
        <div class="form-row">
            <div class="form-group col-md-6">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <div class="input-group-text"><svg class="icon icon-sm"><use xlink:href="{{ asset('svg/sprite.svg#it-user') }}"></use></svg></div>
                    </div>
                    <label for="email">{{ __('Indirizzo email') }}</label>
                    <input type="email" class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}" id="email" name="email" value="{{ old('email') }}" placeholder="{{ __('inserisci il tuo indirizzo email') }}" aria-required="true" required>
                    <div class="invalid-feedback">{{ __('validation.email', ['attribute' => __('validation.attributes.email')]) }}</div>
                </div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-6">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <div class="input-group-text"><svg class="icon icon-sm"><use xlink:href="{{ asset('svg/sprite.svg#it-key') }}"></use></svg></div>
                    </div>
                    <label for="password">{{ __('Password') }}</label>
                    <input type="password" class="form-control{{ $errors->has('password') ? ' is-invalid' : '' }}" id="password" name="password" placeholder="{{ __('inserisci la tua password') }}" aria-required="true" required>
                    <div class="invalid-feedback">{{ __('validation.required', ['attribute' => __('validation.attributes.password')]) }}</div>
                </div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group mb-0 col text-center">
                <button type="submit" class="btn btn-primary">{{ __('Accedi') }}</button><br>
                <a href="{{ route('admin.password.forgot.show') }}">
                    <small>{{ __('Password dimenticata?') }}</small>
                </a>
            </div>
        </div>
    </div>
</form>
@endsection
