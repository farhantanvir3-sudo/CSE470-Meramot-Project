@extends('layouts.app')

@section('title', 'Create your account | Sign Up — Meramot')

@section('content')
<div class="min-h-[85vh] flex items-center justify-center px-4 py-8">
    <div class="flex w-full max-w-[460px] flex-col gap-4">
        
        <!-- Top Logo -->
        <div class="flex w-full items-center justify-center mb-2">
            <a href="/" class="inline-block transition-transform hover:scale-105">
                <img
                    src="/images/meramot.svg"
                    alt="Meramot"
                    class="h-16 w-auto object-contain md:h-20"
                />
            </a>
        </div>

        <!-- ── Meramot Sign-Up Card ── -->
        <div class="rounded-[2rem] border border-white/60 bg-white/90 px-8 py-6 shadow-2xl backdrop-blur">
            
            <div class="mb-4 text-center">
                <h1 class="text-3xl font-bold leading-tight text-[#214c34]">
                    Create your account
                </h1>
                <p class="mt-1 text-sm text-[#5e6d64]">
                    Sign up to request repairs, compare shops, and save viewed items.
                </p>
            </div>

            @if($errors->any())
                <div class="mb-4 rounded-2xl bg-red-50 border border-red-200 px-4 py-3 text-xs text-red-700 space-y-1">
                    @foreach($errors->all() as $err)
                        <div>{{ $err }}</div>
                    @endforeach
                </div>
            @endif

            <!-- ── Distinct "Sign up with Google" Button (Passwordless Customer Registration) ── -->
            <a
                href="/auth/google?intent=signup"
                class="group relative mb-4 flex w-full items-center justify-center gap-3 overflow-hidden rounded-full border border-[var(--border)] bg-white px-4 py-3 text-sm font-semibold text-[#203126] transition-all duration-300 hover:border-[#214c34]/40 hover:shadow-[0_0_20px_-5px_rgba(33,76,52,0.3)]"
            >
                <span class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-[#214c34]/[0.07] to-transparent transition-transform duration-500 group-hover:translate-x-full"></span>
                <svg class="relative h-4 w-4 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#4285F4" d="M23.49 12.27c0-.79-.07-1.54-.19-2.27H12v4.51h6.47c-.29 1.48-1.14 2.73-2.4 3.58v2.98h3.86c2.26-2.09 3.56-5.17 3.56-8.8z"/>
                    <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.86-2.98c-1.08.72-2.45 1.16-4.07 1.16-3.12 0-5.77-2.11-6.72-4.96H1.29v3.09C3.26 21.3 7.31 24 12 24z"/>
                    <path fill="#FBBC05" d="M5.28 14.31c-.24-.72-.38-1.49-.38-2.31s.14-1.59.38-2.31V6.6H1.29C.47 8.24 0 10.06 0 12s.47 3.76 1.29 5.4l3.99-3.09z"/>
                    <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.26 2.7 1.29 6.6l3.99 3.09c.95-2.85 3.6-4.94 6.72-4.94z"/>
                </svg>
                <span class="relative">Sign up with Google</span>
            </a>

            <div class="mb-4 flex items-center gap-3">
                <div class="h-px flex-1 bg-[var(--border)] opacity-40"></div>
                <span class="text-xs uppercase tracking-[0.2em] text-[#5e6d64]">
                    or
                </span>
                <div class="h-px flex-1 bg-[var(--border)] opacity-40"></div>
            </div>

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

                <!-- Password strength checklist -->
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
                    class="w-full rounded-full bg-[#163625] py-3.5 text-sm font-semibold text-white shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#1d4630] hover:shadow-[0_12px_24px_-6px_rgba(22,54,37,0.4)]"
                >
                    Create Account
                </button>
            </form>

            <div class="mt-5 text-center text-xs text-[#5e6d64] space-y-3 border-t border-[var(--border)]/40 pt-4">
                <p>
                    Already have an account?
                    <a href="/login" class="font-bold text-[#214c34] hover:underline">
                        Sign in
                    </a>
                </p>

                <a
                    href="/delivery-agent/register"
                    class="group flex items-center justify-between gap-3 rounded-2xl border border-[#c8dac2] bg-gradient-to-r from-[#f8fbf6] via-white to-[#eef5ea] px-4 py-2.5 text-left shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-[#214c34] hover:shadow-md"
                >
                    <span class="flex items-center gap-2.5 font-bold text-[#1b365d]">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-[#eef5ea] text-[#163625] transition-colors group-hover:bg-[#163625] group-hover:text-[#E4FCD5]">
                            <i class="fa-solid fa-motorcycle text-xs"></i>
                        </span>
                        <span>Apply as a doorstep delivery courier</span>
                    </span>
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-[#c8dac2] bg-white text-[#163625] transition-all duration-200 group-hover:translate-x-0.5 group-hover:bg-[#163625] group-hover:text-[#E4FCD5]">
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </span>
                </a>
            </div>
        </div>

        <!-- ── Golden "Register as a vendor" CTA Tile Below Sign-Up Card ── -->
        <div>
            <a
                href="/vendor/apply"
                class="group flex w-full items-center justify-between gap-3 rounded-[1.25rem] bg-gradient-to-r from-[#D4AF37] via-[#dfb93f] to-[#c9a32c] px-5 py-3.5 text-sm font-bold text-white shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:brightness-105 hover:shadow-[0_12px_26px_-6px_rgba(212,175,55,0.55)]"
            >
                <span class="flex items-center gap-2.5">
                    <i class="fa-solid fa-store"></i>
                    <span>Register as a vendor</span>
                </span>
                <i class="fa-solid fa-arrow-right text-xs transition-transform duration-200 group-hover:translate-x-1"></i>
            </a>
        </div>
    </div>
</div>
<script>
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
</script>
@endsection
