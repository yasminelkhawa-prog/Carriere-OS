@extends('layouts.public')

@section('title', __('public_site.contact.meta_title').' | '.config('app.name'))

@section('content')
    <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="rounded-3xl border border-white/70 bg-white/80 p-6 shadow-sm">
            <p class="text-xs uppercase tracking-[0.2em] text-aura-700/85">{{ __('public_site.contact.eyebrow') }}</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ __('public_site.contact.title') }}</h1>
            <p class="mt-2 text-sm text-slate-600">{{ __('public_site.contact.subtitle') }}</p>

            @if(session('status'))
                <x-toast-alert type="success" class="mt-4">{{ session('status') }}</x-toast-alert>
            @endif

            @if($errors->any())
                <x-toast-alert type="error" class="mt-4">{{ $errors->first() }}</x-toast-alert>
            @endif

            <form method="POST" action="{{ route('public.contact.store') }}" class="mt-5 grid gap-4 md:grid-cols-2">
                @csrf

                {{-- Honeypot field for spam bots. --}}
                <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" value="">

                <x-form-field :label="__('public_site.contact.form.full_name')" name="full_name" required>
                    <input type="text" name="full_name" required value="{{ old('full_name') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm">
                </x-form-field>

                <x-form-field :label="__('public_site.contact.form.email')" name="email" required>
                    <input type="email" name="email" required value="{{ old('email') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm">
                </x-form-field>

                <x-form-field :label="__('public_site.contact.form.phone')" name="phone">
                    <input type="text" name="phone" value="{{ old('phone') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm">
                </x-form-field>

                <x-form-field :label="__('public_site.contact.form.subject')" name="subject" required>
                    <input type="text" name="subject" required value="{{ old('subject') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm">
                </x-form-field>

                <div class="md:col-span-2">
                    <x-form-field :label="__('public_site.contact.form.message')" name="message" required>
                        <textarea name="message" required rows="6" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm">{{ old('message') }}</textarea>
                    </x-form-field>
                </div>

                <div class="md:col-span-2 flex items-center gap-3">
                    <button type="submit" class="rounded-xl bg-success-600 px-5 py-2.5 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                        {{ __('public_site.contact.form.submit') }}
                    </button>
                    <p class="text-xs text-slate-500">{{ __('public_site.contact.form.notice') }}</p>
                </div>
            </form>
        </div>

        <aside class="space-y-4">
            <section class="rounded-2xl border border-white/70 bg-white/80 p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('public_site.contact.details_title') }}</h2>
                <div class="mt-4 space-y-3 text-sm text-slate-600">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">{{ __('public_site.contact.side.email_label') }}</p>
                        <a href="mailto:{{ __('public_site.footer.contact_email') }}" class="mt-1 inline-block text-slate-800 transition-weightless hover:text-aura-700">
                            {{ __('public_site.footer.contact_email') }}
                        </a>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">{{ __('public_site.contact.side.phone_label') }}</p>
                        <a href="tel:+212600526989" class="mt-1 inline-block text-slate-800 transition-weightless hover:text-aura-700">
                            {{ __('public_site.footer.contact_phone') }}
                        </a>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">{{ __('public_site.contact.side.address_label') }}</p>
                        <p class="mt-1 text-slate-800">{{ __('public_site.footer.contact_address') }}</p>
                    </div>
                </div>
            </section>
            <section class="rounded-2xl border border-white/70 bg-white/80 p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('public_site.contact.side.quick_title') }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ __('public_site.contact.side.quick_text') }}</p>
                <a href="{{ route('public.jobs.index') }}" class="mt-4 inline-flex rounded-xl border border-aura-300/50 bg-white px-4 py-2 text-sm font-semibold text-aura-900 transition-weightless hover:bg-aura-50">
                    {{ __('public_site.nav.browse_jobs') }}
                </a>
            </section>
            <section class="rounded-2xl border border-white/70 bg-white/80 p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('public_site.contact.side.hours_title') }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ __('public_site.contact.side.hours_text') }}</p>
            </section>
        </aside>
    </section>
@endsection
