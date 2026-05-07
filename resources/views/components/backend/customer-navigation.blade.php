{{-- FILE: resources\views\backend\customer\livewire\customer-navigation.blade.php --}}
<x-sections.vertical-nav>
    <li>
        <div class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-3 ml-2 mt-2">Kundenbereich</div>
        <ul role="list" class="-mx-2 space-y-1">
            <x-forms.list-item route="/customer/dashboard" title="Dashboard" pageName="dashboard" icon="home"/>
            <x-forms.list-item route="/orders" title="Bestellungen" pageName="orders" icon="shopping-bag"/>
            <x-forms.list-item route="/invoices" title="Rechnungen" pageName="invoices" icon="document-text"/>
            
            @php
                $user = auth('customer')->user();
                $hasOptedIn = false;
                if ($user) {
                    $profile = \App\Models\Customer\CustomerGamification::where('customer_id', $user->id)->first();
                    if ($profile && $profile->is_active) {
                        $hasOptedIn = true;
                    }
                }
            @endphp

            @if($hasOptedIn)
                <li x-data="{ expanded: {{ request()->is('gamification/profile') || request()->is('games') ? 'true' : 'false' }} }">
                    <button @click="expanded = !expanded" class="group flex items-center justify-between w-full gap-x-3 rounded-xl p-2.5 text-sm font-semibold transition-all duration-300 {{ request()->is('gamification/profile') || request()->is('games') ? 'bg-primary/10 text-primary shadow-[0_0_15px_rgba(197,160,89,0.15)]' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                        <div class="flex items-center gap-x-3">
                            <x-heroicon-o-puzzle-piece class="w-5 h-5 shrink-0 transition-transform duration-300 {{ request()->is('gamification/profile') || request()->is('games') ? 'text-primary' : 'text-gray-500 group-hover:text-white group-hover:scale-110' }}"/>
                            <span>Spiele</span>
                        </div>
                        <x-heroicon-s-chevron-right class="w-4 h-4 transition-transform duration-300" x-bind:class="expanded ? 'rotate-90' : ''"/>
                    </button>
                    <ul x-show="expanded" x-transition.opacity.duration.300ms style="display: none;" class="mt-1 space-y-1 pl-11">
                        <li>
                            <a href="/gamification/profile" class="block rounded-lg py-2 pr-2 pl-3 text-sm leading-6 transition-colors {{ request()->is('gamification/profile') ? 'text-primary bg-primary/5 font-semibold shadow-[0_0_15px_rgba(197,160,89,0.15)]' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                                Profil
                            </a>
                        </li>
                        <li>
                            <a href="/games" class="block rounded-lg py-2 pr-2 pl-3 text-sm leading-6 transition-colors {{ request()->is('games') ? 'text-primary bg-primary/5 font-semibold shadow-[0_0_15px_rgba(197,160,89,0.15)]' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                                Manufaktur Spiele
                            </a>
                        </li>
                    </ul>
                </li>
                <x-forms.list-item route="/ranking" title="Ranking" pageName="ranking" icon="trophy"/>
            @endif
            
            <x-forms.list-item route="/support" title="Support" pageName="support" icon="lifebuoy"/>
            <x-forms.list-item route="/customer/profile" title="Mein Profil" pageName="profile" icon="user-circle"/>
        </ul>
    </li>
</x-sections.vertical-nav>
