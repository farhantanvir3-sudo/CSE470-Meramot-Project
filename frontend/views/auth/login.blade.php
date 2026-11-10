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

        <!-- ── Authentic AuthCard (Exact match to AuthCard.tsx from commit 8e44218) ── -->
        <div class="rounded-[2rem] border border-white/60 bg-white/90 px-8 py-6 shadow-2xl backdrop-blur">
            
            <div class="mb-4 text-center">
                <h1 class="text-3xl font-bold leading-tight text-[#214c34]">
                    Welcome back
                </h1>
                <p class="mt-1 text-sm text-[#5e6d64]">
                    Sign in to continue to your dashboard.
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
                    <a href="#" class="hover:underline">Forgot password?</a>
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

                <!-- Delivery partner link as navy text on sign-in (Commit d9490a9 by Farhan) -->
                <p>
                    <a href="/auth/demo?role=delivery" class="font-semibold text-[#1b365d] hover:underline">
                        <i class="fa-solid fa-motorcycle mr-1"></i> Delivery partner login &rarr;
                    </a>
                </p>

                <p>
                    <a href="/auth/demo?role=vendor" class="text-xs text-emerald-800 hover:underline">
                        <i class="fa-solid fa-store mr-1"></i> Partner repair shop login &rarr;
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
