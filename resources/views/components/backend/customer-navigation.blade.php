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
                <x-forms.list-item route="/games" title="Manufaktur Spiele" pageName="games" icon="puzzle-piece"/>
                <x-forms.list-item route="/ranking" title="Ranking" pageName="ranking" icon="trophy"/>
            @endif
            
            <x-forms.list-item route="/support" title="Support" pageName="support" icon="lifebuoy"/>
            <x-forms.list-item route="/customer/profile" title="Mein Profil" pageName="profile" icon="user-circle"/>
        </ul>
    </li>
</x-sections.vertical-nav>
