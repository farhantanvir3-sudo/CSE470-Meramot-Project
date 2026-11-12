@extends('layouts.app')

@section('title', 'Welcome back | Sign In — Meramot')

@section('content')
<div class="min-h-[80vh] flex items-center justify-center px-4 py-8">
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
                <div class="mb-4 rounded-2xl bg-red-50 border border-red-200 px-4 py-3 text-xs text-red-700 space-y-1">
                    @foreach($errors->all() as $err)
                        <div>{{ $err }}</div>
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
                        value="{{ old('identifier', 'customer@meramot.demo') }}"
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
                            value="password123"
                            required
                            class="w-full rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm outline-none transition focus:border-[#214c34] focus:ring-2 focus:ring-[#214c34]/20"
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
                    <a href="/forgot-password" class="hover:underline">Forgot password?</a>
                </div>

                <button
                    type="submit"
                    class="w-full rounded-full bg-[var(--accent-dark)] py-3 text-sm font-semibold text-white shadow transition hover:opacity-95"
                >
                    Sign In
                </button>
            </form>

            <div class="mt-5 text-center text-xs text-[#5e6d64] space-y-2 border-t border-[var(--border)]/40 pt-4">
                <p>
                    Don't have an account?
                    <a href="/register" class="font-semibold text-[#214c34] hover:underline">
                        Sign up
                    </a>
                </p>

                <p>
                    <a href="/auth/demo?role=delivery" class="font-semibold text-[#1b365d] hover:underline">
                        <i class="fa-solid fa-motorcycle mr-1"></i> Delivery partner login &rarr;
                    </a>
                </p>

                <p>
                    <a href="/vendor/apply" class="text-xs text-emerald-800 hover:underline">
                        <i class="fa-solid fa-store mr-1"></i> Apply as a partner repair shop &rarr;
                    </a>
                </p>
            </div>

            <!-- Quick Dev Helper Chips -->
            <div class="mt-4 pt-3 border-t border-[var(--border)]/30 text-center">
                <span class="text-[10px] uppercase font-bold text-[#5e6d64] block mb-2">1-Click Demo Login</span>
                <div class="flex justify-center gap-1.5 text-[10px]">
                    <a href="/auth/demo?role=customer" class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-900 font-semibold hover:bg-emerald-200">Customer</a>
                    <a href="/auth/demo?role=vendor" class="px-2.5 py-1 rounded-full bg-amber-100 text-amber-900 font-semibold hover:bg-amber-200">Vendor</a>
                    <a href="/auth/demo?role=delivery" class="px-2.5 py-1 rounded-full bg-sky-100 text-sky-900 font-semibold hover:bg-sky-200">Rider</a>
                    <a href="/auth/demo?role=admin" class="px-2.5 py-1 rounded-full bg-rose-100 text-rose-900 font-semibold hover:bg-rose-200">Admin</a>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
