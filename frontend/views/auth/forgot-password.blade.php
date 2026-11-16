@extends('layouts.app')

@section('title', 'Set New Password | Meramot')

@section('content')
<div
    class="min-h-[80vh] flex items-center justify-center px-4 py-8"
    x-data="{
        password: '',
        passwordConfirmation: '',
        pwFocused: false,
        showPass: false,
        showConfirmPass: false
    }"
>
    <div class="flex w-full max-w-[460px] flex-col gap-6">

        <div class="flex w-full items-center justify-center">
            <a href="/" class="inline-block transition-transform hover:scale-105">
                <img
                    src="/images/meramot.svg"
                    alt="Meramot"
                    class="h-16 w-auto object-contain md:h-20"
                />
            </a>
        </div>

        <div class="relative rounded-lg border border-white/60 bg-white/95 px-6 sm:px-8 py-7 shadow-2xl backdrop-blur">
            <a
                href="/login"
                title="Back to Sign In"
                aria-label="Back to Sign In"
                class="group absolute left-5 top-6 inline-flex h-10 w-10 items-center justify-center rounded-full border border-[#c8dac2] bg-white text-[#163625] shadow-sm transition-all duration-200 hover:border-[#163625] hover:bg-[#163625] hover:text-[#E4FCD5]"
            >
                <i class="fa-solid fa-arrow-left text-xs transition-transform duration-200 group-hover:-translate-x-0.5"></i>
            </a>

            <div class="mb-5 text-center px-8">
                <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 border border-emerald-200 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-emerald-900 mb-2">
                    <i class="fa-solid fa-shield-halved"></i> Identity Verified
                </span>
                <h1 class="text-2xl sm:text-3xl font-bold leading-tight text-[#214c34]">
                    Set New Password
                </h1>
                <p class="mt-1.5 text-xs sm:text-sm text-[#5e6d64]">
                    Create a strong new password for your account.
                </p>
                @if(!empty($maskedEmail))
                    <p class="mt-2 inline-flex items-center gap-1.5 rounded-md border border-[#cbe0c2] bg-[#f8fbf6] px-3 py-1 font-mono text-xs font-bold text-[#163625]">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        <span>{{ $maskedEmail }}</span>
                    </p>
                @endif
            </div>

            @if($errors->any())
                <div class="mb-4 rounded-lg bg-red-50/90 border border-red-200 px-4 py-3 text-xs font-medium text-red-800 space-y-1">
                    @foreach($errors->all() as $err)
                        <div class="flex items-start gap-2">
                            <i class="fa-solid fa-circle-exclamation mt-0.5 shrink-0 text-red-600"></i>
                            <span>{{ $err }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="/forgot-password/reset" class="space-y-4">
                @csrf
                <input type="hidden" name="reset_token" value="{{ $token ?? '' }}" />

                <div>
                    <label class="mb-1 block text-xs font-bold uppercase tracking-wider text-[#214c34]">
                        New Password
                    </label>
                    <div class="relative">
                        <input
                            :type="showPass ? 'text' : 'password'"
                            name="password"
                            x-model="password"
                            @focus="pwFocused = true"
                            @blur="pwFocused = false"
                            autocomplete="new-password"
                            minlength="8"
                            required
                            autofocus
                            placeholder="Minimum 8 characters"
                            class="w-full rounded-lg border border-[#c8dac2] bg-white pl-4 pr-16 py-3 text-sm text-[#163625] outline-none transition focus:border-[#214c34] focus:ring-2 focus:ring-[#214c34]/20"
                        />
                        <button
                            type="button"
                            @click="showPass = !showPass"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-semibold text-[#5e6d64] hover:text-[#214c34]"
                        >
                            <span x-text="showPass ? 'Hide' : 'Show'">Show</span>
                        </button>
                    </div>

                    @include('partials.password-strength-meter', [
                        'passwordExpr' => 'password',
                        'confirmExpr' => 'passwordConfirmation',
                        'focusedExpr' => 'pwFocused',
                        'evalVar' => 'resetPwEval',
                    ])
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold uppercase tracking-wider text-[#214c34]">
                        Confirm New Password
                    </label>
                    <div class="relative">
                        <input
                            :type="showConfirmPass ? 'text' : 'password'"
                            name="password_confirmation"
                            x-model="passwordConfirmation"
                            autocomplete="new-password"
                            minlength="8"
                            required
                            placeholder="Re-enter new password"
                            class="w-full rounded-lg border border-[#c8dac2] bg-white pl-4 pr-16 py-3 text-sm text-[#163625] outline-none transition focus:border-[#214c34] focus:ring-2 focus:ring-[#214c34]/20"
                        />
                        <button
                            type="button"
                            @click="showConfirmPass = !showConfirmPass"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-semibold text-[#5e6d64] hover:text-[#214c34]"
                        >
                            <span x-text="showConfirmPass ? 'Hide' : 'Show'">Show</span>
                        </button>
                    </div>
                </div>

                <button
                    type="submit"
                    class="w-full rounded-full bg-[#163625] py-3.5 text-sm font-bold text-white shadow-md transition-all duration-200 hover:bg-[#1d4630] hover:shadow-[0_12px_24px_-6px_rgba(22,54,37,0.4)]"
                >
                    Save New Password
                </button>
            </form>

            <div class="mt-5 pt-4 border-t border-[var(--border)]/40 text-center text-xs text-[#5e6d64]">
                <a href="/login" class="font-bold text-[#214c34] hover:underline">Back to Sign In</a>
            </div>
        </div>
    </div>
</div>
@endsection
