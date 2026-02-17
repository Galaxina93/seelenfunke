<div class="p-8 md:p-10 space-y-8">
    {{-- Header --}}
    <div class="text-center">
        <h1 class="text-2xl font-serif font-bold leading-tight tracking-tight text-slate-800 md:text-3xl">
            Passwort erneuern
        </h1>
        <p class="text-slate-500 mt-3 text-sm leading-relaxed">
            Gib deine E-Mail und dein neues Wunschpasswort ein, um deinen Zugang wiederherzustellen.
        </p>
    </div>

    <div class="pt-2">
        <x-forms.form submit="submit" :grid="false">

            <div class="space-y-5">
                {{-- E-Mail --}}
                <div class="space-y-1">
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-400 ml-1">E-Mail Adresse</label>
                    <x-forms.email class="w-full !rounded-xl !border-slate-200 !bg-slate-50/50 focus:!bg-white focus:!ring-[#C5A059] focus:!border-[#C5A059] transition-all py-3" />
                </div>

                {{-- Passwort Felder --}}
                <div class="grid grid-cols-1 gap-5">
                    <div class="space-y-1">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-400 ml-1">Neues Passwort</label>
                        <x-forms.password title="" variable="password" class="!rounded-xl !border-slate-200 !bg-slate-50/50 focus:!bg-white focus:!ring-[#C5A059] py-3" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-400 ml-1">Bestätigung</label>
                        <x-forms.password title="" variable="passwordConfirm" class="!rounded-xl !border-slate-200 !bg-slate-50/50 focus:!bg-white focus:!ring-[#C5A059] py-3" />
                    </div>
                </div>
            </div>

            {{-- Button --}}
            <div class="mt-10">
                <x-forms.button
                    title="Passwort speichern"
                    category="primary"
                    type="submit"
                />
            </div>

            {{-- Feedback Sektion --}}
            <div class="mt-6">
                @if (session('status'))
                    <div class="rounded-xl border-none bg-emerald-50 text-emerald-700 p-4 text-sm font-medium animate-pulse">
                        <x-alerts.message sessionVariable="status" />
                    </div>
                @endif

                <x-alerts.errors class="rounded-xl bg-rose-50 text-rose-700 p-4 text-sm" />
            </div>

        </x-forms.form>
    </div>

    {{-- Hilfreiche Links --}}
    <div class="text-center pt-6 border-t border-slate-50">
        <a href="{{ route('login') }}" class="text-sm font-semibold text-slate-400 hover:text-[#C5A059] transition-colors flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            Zurück zum Login
        </a>
    </div>
</div>
