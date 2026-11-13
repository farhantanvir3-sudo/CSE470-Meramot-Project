@extends('layouts.app')

@section('title', 'Welcome back | Sign In — Meramot')

@section('content')
<div
    class="min-h-[80vh] flex items-center justify-center px-4 py-8"
    x-data="{
        forgotModalOpen: {{ session('forgot_password_sent_email') ? 'true' : 'false' }},
        forgotStep: '{{ session('forgot_password_sent_email') ? 'sent' : 'form' }}',
        forgotEmail: @js(session('forgot_password_sent_email', '')),
        forgotSending: false,
        forgotError: '',
        openForgotModal() {
            const loginInput = document.querySelector('input[name=login]')?.value?.trim() || '';
            this.forgotEmail = loginInput.includes('@') ? loginInput : '';
            this.forgotError = '';
            this.forgotSending = false;
            this.forgotStep = 'form';
            this.forgotModalOpen = true;
        },
        async submitForgotPassword() {
            this.forgotError = '';
            const email = (this.forgotEmail || '').trim();
            if (!email || !email.includes('@')) {
                this.forgotError = 'Please enter a valid email address.';
                return;
            }
            this.forgotSending = true;
            try {
                const res = await fetch('/forgot-password', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ email })
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this.forgotError = data?.errors?.email?.[0] || data?.message || 'Unable to send recovery email right now.';
                    return;
                }
                this.forgotEmail = data.email || email;
                this.forgotStep = 'sent';
            } catch (e) {
                this.forgotError = 'Network error. Please try again.';
            } finally {
                this.forgotSending = false;
            }
        }
    }"
>
    <div class="flex w-full max-w-[440px] flex-col gap-6">
        
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

        <!-- ── Meramot Sign-In Card ── -->
        <div class="rounded-[2rem] border border-white/60 bg-white/90 px-8 py-6 shadow-2xl backdrop-blur">
            
            <div class="mb-4 text-center">
                <h1 class="text-3xl font-bold leading-tight text-[#214c34]">
                    Welcome back
                </h1>
                <p class="mt-1 text-sm text-[#5e6d64]">
                    Sign in to continue to your dashboard.
                </p>
            </div>

            @if(session('success'))
                <div class="mb-4 rounded-2xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-xs font-medium text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 rounded-2xl bg-red-50/90 border border-red-200 px-4 py-3 text-xs font-medium text-red-800 space-y-1.5 shadow-sm">
                    @foreach($errors->all() as $err)
                        <div class="flex items-start gap-2 leading-relaxed">
                            <i class="fa-solid fa-circle-exclamation mt-0.5 shrink-0 text-red-600"></i>
                            <span>{{ $err }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <!-- ── Distinct "Sign in with Google" Button (Requires prior Google Sign-Up) ── -->
            <a
                href="/auth/google?intent=login"
                class="group relative mb-4 flex w-full items-center justify-center gap-3 overflow-hidden rounded-full border border-[var(--border)] bg-white px-4 py-3 text-sm font-semibold text-[#203126] transition-all duration-300 hover:border-[#214c34]/40 hover:shadow-[0_0_20px_-5px_rgba(33,76,52,0.3)]"
            >
                <span class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-[#214c34]/[0.07] to-transparent transition-transform duration-500 group-hover:translate-x-full"></span>
                <svg class="relative h-4 w-4 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#4285F4" d="M23.49 12.27c0-.79-.07-1.54-.19-2.27H12v4.51h6.47c-.29 1.48-1.14 2.73-2.4 3.58v2.98h3.86c2.26-2.09 3.56-5.17 3.56-8.8z"/>
                    <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.86-2.98c-1.08.72-2.45 1.16-4.07 1.16-3.12 0-5.77-2.11-6.72-4.96H1.29v3.09C3.26 21.3 7.31 24 12 24z"/>
                    <path fill="#FBBC05" d="M5.28 14.31c-.24-.72-.38-1.49-.38-2.31s.14-1.59.38-2.31V6.6H1.29C.47 8.24 0 10.06 0 12s.47 3.76 1.29 5.4l3.99-3.09z"/>
                    <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.26 2.7 1.29 6.6l3.99 3.09c.95-2.85 3.6-4.94 6.72-4.94z"/>
                </svg>
                <span class="relative">Sign in with Google</span>
            </a>

            <div class="mb-4 flex items-center gap-3">
                <div class="h-px flex-1 bg-[var(--border)] opacity-40"></div>
                <span class="text-xs uppercase tracking-[0.2em] text-[#5e6d64]">
                    or
                </span>
                <div class="h-px flex-1 bg-[var(--border)] opacity-40"></div>
            </div>

            <form method="POST" action="/login" class="space-y-4">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#214c34]">
                        Username or Email
                    </label>
                    <input
                        type="text"
                        name="identifier"
                        value="{{ old('identifier') }}"
                        required
                        class="w-full rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm outline-none transition focus:border-[#214c34] focus:ring-2 focus:ring-[#214c34]/20"
                        placeholder="Username or email"
                    />
                </div>

                <div x-data="{ show: false }">
                    <label class="mb-1 block text-sm font-medium text-[#214c34]">
                        Password
                    </label>
                    <div class="relative">
                        <input
                            :type="show ? 'text' : 'password'"
                            name="password"
                            required
                            class="w-full rounded-2xl border border-[var(--border)] bg-white pl-4 pr-16 py-3 text-sm outline-none transition focus:border-[#214c34] focus:ring-2 focus:ring-[#214c34]/20"
                            placeholder="Enter your password"
                        />
                        <button 
                            type="button" 
                            @click="show = !show" 
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-xs text-[var(--muted-foreground)] hover:text-[var(--foreground)]"
                        >
                            <span x-text="show ? 'Hide' : 'Show'">Show</span>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs text-[#5e6d64]">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" class="rounded accent-[#214c34]" checked>
                        <span>Remember me</span>
                    </label>
                    <a href="/forgot-password" @click.prevent="openForgotModal()" class="hover:underline font-medium text-[#214c34]">Forgot password?</a>
                </div>

                <button
                    type="submit"
                    class="w-full rounded-full bg-[#163625] py-3.5 text-sm font-semibold text-white shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#1d4630] hover:shadow-[0_12px_24px_-6px_rgba(22,54,37,0.4)]"
                >
                    Sign In
                </button>
            </form>

            <div class="mt-5 text-center text-xs text-[#5e6d64] space-y-2.5 border-t border-[var(--border)]/40 pt-4">
                <p class="pb-1">
                    Don't have an account?
                    <a href="/register" class="font-bold text-[#214c34] hover:underline">
                        Sign up
                    </a>
                </p>

                <div class="grid grid-cols-1 gap-2 text-left">
                    <a
                        href="/delivery/login"
                        class="group flex items-center justify-between gap-3 rounded-2xl border border-[#c8dac2] bg-gradient-to-r from-[#f8fbf6] via-white to-[#eef5ea] px-4 py-2.5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-[#214c34] hover:shadow-md"
                    >
                        <span class="flex items-center gap-2.5 font-bold text-[#1b365d]">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-[#eef5ea] text-[#163625] transition-colors group-hover:bg-[#163625] group-hover:text-[#E4FCD5]">
                                <i class="fa-solid fa-motorcycle text-xs"></i>
                            </span>
                            <span>Delivery partner login</span>
                        </span>
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-[#c8dac2] bg-white text-[#163625] transition-all duration-200 group-hover:translate-x-0.5 group-hover:bg-[#163625] group-hover:text-[#E4FCD5]">
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </span>
                    </a>

                    <a
                        href="/vendor/apply"
                        class="group flex items-center justify-between gap-3 rounded-2xl border border-[#c8dac2] bg-gradient-to-r from-[#f8fbf6] via-white to-[#eef5ea] px-4 py-2.5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-[#214c34] hover:shadow-md"
                    >
                        <span class="flex items-center gap-2.5 font-bold text-emerald-900">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-[#eef5ea] text-emerald-800 transition-colors group-hover:bg-[#163625] group-hover:text-[#E4FCD5]">
                                <i class="fa-solid fa-store text-xs"></i>
                            </span>
                            <span>Apply as a partner repair shop</span>
                        </span>
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-[#c8dac2] bg-white text-[#163625] transition-all duration-200 group-hover:translate-x-0.5 group-hover:bg-[#163625] group-hover:text-[#E4FCD5]">
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </span>
                    </a>
                </div>
            </div>

            <!-- Quick Dev Helper Chips -->
            <div class="mt-4 pt-3 border-t border-[var(--border)]/30 text-center">
                <span class="text-[10px] uppercase font-bold text-[#5e6d64] block mb-2">1-Click Demo Login</span>
                <div class="flex justify-center gap-1.5 text-[10px]">
                    <a href="/auth/demo?role=customer" class="px-3 py-1 rounded-full bg-emerald-100 text-emerald-900 font-semibold shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-emerald-200 hover:shadow">Customer</a>
                    <a href="/auth/demo?role=vendor" class="px-3 py-1 rounded-full bg-amber-100 text-amber-900 font-semibold shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-amber-200 hover:shadow">Vendor</a>
                    <a href="/auth/demo?role=delivery" class="px-3 py-1 rounded-full bg-sky-100 text-sky-900 font-semibold shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-sky-200 hover:shadow">Rider</a>
                    <a href="/auth/demo?role=admin" class="px-3 py-1 rounded-full bg-rose-100 text-rose-900 font-semibold shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-rose-200 hover:shadow">Admin</a>
                </div>
            </div>

        </div>
    </div>

    <!-- ── Forgot Password Dialog & Email Sent Confirmation Pop-Up Tile ── -->
    <div
        x-show="forgotModalOpen"
        x-cloak
        @keydown.escape.window="if (forgotModalOpen && !forgotSending) forgotModalOpen = false"
        class="fixed inset-0 z-[110] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="forgot-password-modal-title"
    >
        <div
            x-show="forgotModalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute inset-0 bg-[#0b1f14]/55 backdrop-blur-sm"
            @click="if (!forgotSending) forgotModalOpen = false"
        ></div>

        <div
            x-show="forgotModalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95 translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-2"
            class="relative z-[111] w-full max-w-md overflow-hidden rounded-[2rem] border border-[var(--border)] bg-[var(--card)] shadow-[0_24px_64px_rgba(11,31,20,0.28)]"
        >
            <div class="h-1.5 w-full bg-gradient-to-r from-emerald-500 via-[#214c34] to-emerald-400"></div>

            <!-- Step 1: Enter Email -->
            <div x-show="forgotStep === 'form'" class="p-7 sm:p-8">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-emerald-200 bg-emerald-50 text-[#214c34]">
                            <i class="fa-solid fa-key text-base"></i>
                        </div>
                        <div>
                            <h2 id="forgot-password-modal-title" class="text-lg font-extrabold text-[var(--foreground)]">Reset your password</h2>
                            <p class="text-xs text-[var(--muted-foreground)]">Enter your account email to receive recovery instructions.</p>
                        </div>
                    </div>
                    <button type="button" @click="forgotModalOpen = false" class="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--mint-100)] text-[var(--foreground)] hover:bg-[var(--mint-200)] transition" aria-label="Close">✕</button>
                </div>

                <form @submit.prevent="submitForgotPassword()" class="mt-5 space-y-4">
                    <template x-if="forgotError">
                        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-2.5 text-xs font-medium text-red-800 flex items-center gap-2">
                            <i class="fa-solid fa-circle-exclamation text-red-600"></i>
                            <span x-text="forgotError"></span>
                        </div>
                    </template>

                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-[var(--foreground)]">Email address</label>
                        <input
                            type="email"
                            x-model="forgotEmail"
                            required
                            placeholder="name@example.com"
                            class="w-full rounded-full border border-[#d7d7d7] bg-white px-5 py-3 text-sm text-[#163625] outline-none transition focus:border-[#214c34]"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-1">
                        <button
                            type="button"
                            @click="forgotModalOpen = false"
                            :disabled="forgotSending"
                            class="rounded-full border border-[var(--border)] bg-[var(--background)] px-5 py-2.5 text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-50)] transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="forgotSending"
                            class="inline-flex items-center gap-2 rounded-full bg-[#163625] px-6 py-2.5 text-xs font-bold text-white shadow-md hover:bg-[#1d4630] transition disabled:opacity-60"
                        >
                            <i class="fa-solid" :class="forgotSending ? 'fa-spinner fa-spin' : 'fa-paper-plane'"></i>
                            <span x-text="forgotSending ? 'Sending...' : 'Send Recovery Email'"></span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Step 2: Email Sent Successfully Pop-Up Tile -->
            <div x-show="forgotStep === 'sent'" class="p-7 sm:p-8 text-center">
                <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-600 shadow-inner">
                    <i class="fa-solid fa-envelope-circle-check text-2xl"></i>
                </div>

                <h2 class="text-xl sm:text-2xl font-extrabold tracking-tight text-[var(--foreground)]">
                    Email Sent Successfully
                </h2>

                <div class="mt-3 inline-flex max-w-full items-center gap-2 rounded-full border border-[var(--border)] bg-[var(--background)] px-3.5 py-1.5 text-xs font-bold text-[var(--foreground)] shadow-sm">
                    <span class="h-2 w-2 shrink-0 rounded-full bg-emerald-500"></span>
                    <span class="truncate" x-text="forgotEmail"></span>
                </div>

                <p class="mt-3.5 text-sm leading-relaxed text-[var(--muted-foreground)]">
                    If an account is registered with this address, we've sent password recovery instructions to your inbox. Please check your spam folder if it doesn't arrive shortly.
                </p>

                <div class="mt-6">
                    <button
                        type="button"
                        @click="forgotModalOpen = false"
                        class="w-full rounded-full bg-[#163625] px-6 py-3 text-sm font-bold text-white shadow-md hover:bg-[#1d4630] transition"
                    >
                        Done
                    </button>
                </div>
            </div>
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
