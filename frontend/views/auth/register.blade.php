@extends('layouts.app')

@section('title', 'Create your account | Sign Up — Meramot')

@section('content')
<div class="min-h-[85vh] flex items-center justify-center px-4 py-8">
    <div class="flex w-full max-w-[460px] flex-col gap-6">
        
        <!-- Top Logo -->
        <div class="flex w-full items-center justify-center">
            <a href="/" class="inline-block transition-transform hover:scale-105">
                <img
                    src="/images/meramot.svg"
                    alt="Meramot"
                    class="h-16 w-auto object-contain md:h-20"
                />
            </a>
        </div>

        <!-- ── Authentic AuthCard (Exact match to AuthCard.tsx from commit 8e44218) ── -->
        <div class="rounded-[2rem] border border-white/60 bg-white/90 px-8 py-6 shadow-2xl backdrop-blur">
            
            <div class="mb-4 text-center">
                <h1 class="text-3xl font-bold leading-tight text-[#214c34]">
                    Create your account
                </h1>
                <p class="mt-1 text-sm text-[#5e6d64]">
                    Sign up to request repairs, compare shops, and save viewed items.
                </p>
            </div>

            <!-- ── Signature Google Button with Green Gradient Shimmer (Commit 8e44218a3c09) ── -->
            <button
                type="button"
                onclick="window.location.href='/auth/demo?role=customer'"
                class="group relative mb-4 flex w-full items-center justify-center gap-3 overflow-hidden rounded-full border border-[var(--border)] bg-white px-4 py-3 text-sm font-semibold text-[#203126] transition-all duration-300 hover:border-[#214c34]/40 hover:shadow-[0_0_20px_-5px_rgba(33,76,52,0.3)]"
            >
                <span class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-[#214c34]/[0.07] to-transparent transition-transform duration-500 group-hover:translate-x-full"></span>
                <span class="relative font-bold text-base text-[#4285F4]">G</span>
                <span class="relative">Continue with Google</span>
            </button>

            <div class="mb-4 flex items-center gap-3">
                <div class="h-px flex-1 bg-[var(--border)] opacity-40"></div>
                <span class="text-xs uppercase tracking-[0.2em] text-[#5e6d64]">
                    or
                </span>
                <div class="h-px flex-1 bg-[var(--border)] opacity-40"></div>
            </div>

            @if($errors->any())
                <div class="mb-4 rounded-2xl bg-red-50 border border-red-200 px-4 py-3 text-xs text-red-600">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="/register" class="space-y-4" x-data="{
                password: '',
                confirm: '',
                focused: false,
                get hasLength() { return this.password.length >= 8; },
                get hasUpper() { return /[A-Z]/.test(this.password); },
                get hasLower() { return /[a-z]/.test(this.password); },
                get hasNumber() { return /[0-9]/.test(this.password); },
                get hasSpecial() { return /[^A-Za-z0-9]/.test(this.password); }
            }">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#214c34]">
                        Full name
                    </label>
                    <input
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        required
                        class="w-full rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm outline-none transition focus:border-[#214c34] focus:ring-2 focus:ring-[#214c34]/20"
                        placeholder="Your full name"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-[#214c34]">
                        Username
                    </label>
                    <input
                        type="text"
                        name="username"
                        value="{{ old('username') }}"
                        class="w-full rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm outline-none transition focus:border-[#214c34] focus:ring-2 focus:ring-[#214c34]/20"
                        placeholder="Choose a username"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-[#214c34]">
                        Email
                    </label>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        class="w-full rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm outline-none transition focus:border-[#214c34] focus:ring-2 focus:ring-[#214c34]/20"
                        placeholder="you@example.com"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-[#214c34]">
                        Phone number
                    </label>
                    <input
                        type="tel"
                        name="phone"
                        value="{{ old('phone') }}"
                        class="w-full rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm outline-none transition focus:border-[#214c34] focus:ring-2 focus:ring-[#214c34]/20"
                        placeholder="01XXXXXXXXX"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-[#214c34]">
                        Password
                    </label>
                    <input
                        type="password"
                        name="password"
                        x-model="password"
                        @focus="focused = true"
                        required
                        class="w-full rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm outline-none transition focus:border-[#214c34] focus:ring-2 focus:ring-[#214c34]/20"
                        placeholder="Enter your password"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-[#214c34]">
                        Confirm password
                    </label>
                    <input
                        type="password"
                        name="password_confirmation"
                        x-model="confirm"
                        required
                        class="w-full rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm outline-none transition focus:border-[#214c34] focus:ring-2 focus:ring-[#214c34]/20"
                        placeholder="Re-enter your password"
                    />
                    <p x-show="confirm.length > 0 && password !== confirm" class="mt-1 text-xs font-medium text-red-500 pl-1">
                        Passwords do not match.
                    </p>
                </div>

                <!-- Password strength checklist (Exact match to AuthCard.tsx) -->
                <div x-show="focused || password.length > 0" class="rounded-2xl bg-[#f6faf4] px-4 py-3 text-xs text-[#58725f]">
                    <p class="font-bold mb-1">Password must include:</p>
                    <ul class="space-y-1">
                        <li :class="hasLength ? 'text-emerald-700 font-semibold' : ''"><span x-text="hasLength ? '✓' : '•'"></span> At least 8 characters</li>
                        <li :class="hasUpper ? 'text-emerald-700 font-semibold' : ''"><span x-text="hasUpper ? '✓' : '•'"></span> An uppercase letter</li>
                        <li :class="hasLower ? 'text-emerald-700 font-semibold' : ''"><span x-text="hasLower ? '✓' : '•'"></span> A lowercase letter</li>
                        <li :class="hasNumber ? 'text-emerald-700 font-semibold' : ''"><span x-text="hasNumber ? '✓' : '•'"></span> A number</li>
                        <li :class="hasSpecial ? 'text-emerald-700 font-semibold' : ''"><span x-text="hasSpecial ? '✓' : '•'"></span> A special character</li>
                    </ul>
                </div>

                <button
                    type="submit"
                    class="w-full rounded-full bg-[var(--accent-dark)] py-3 text-sm font-semibold text-white shadow transition hover:opacity-95"
                >
                    Create Account
                </button>
            </form>

            <div class="mt-5 text-center text-xs text-[#5e6d64] space-y-2 border-t border-[var(--border)]/40 pt-4">
                <p>
                    Already have an account?
                    <a href="/login" class="font-semibold text-[#214c34] hover:underline">
                        Sign in
                    </a>
                </p>

                <!-- Partner links shown on signup (Commit 1eb676e by Farhan) -->
                <div class="pt-2 flex flex-col gap-1.5 text-[11px]">
                    <a href="/vendor/onboarding" class="text-emerald-800 font-medium hover:underline">
                        <i class="fa-solid fa-store mr-1"></i> Register your repair shop as a partner &rarr;
                    </a>
                    <a href="/delivery-agent/register" class="text-[#1b365d] font-medium hover:underline">
                        <i class="fa-solid fa-motorcycle mr-1"></i> Apply as a doorstep delivery courier &rarr;
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
