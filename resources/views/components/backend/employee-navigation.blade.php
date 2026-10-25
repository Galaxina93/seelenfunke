{{-- FILE: resources\views\components\backend\employee-navigation.blade.php --}}
@php
    $currentEmployee = auth('employee')->user();
    $isInfluencer = $currentEmployee && method_exists($currentEmployee, 'isInfluencer') && $currentEmployee->isInfluencer();
@endphp

<x-sections.vertical-nav>
    @if($isInfluencer)
        <li>
            <div class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-3 ml-2 mt-2">Creator Portal</div>
            <ul role="list" class="-mx-2 space-y-1">
                <x-forms.list-item route="/employee/influencer/dashboard" title="Performance & Links" pageName="influencer/dashboard" icon="sparkles"/>
            </ul>
        </li>
        <li>
            <div class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-3 ml-2 mt-6">Direktlinks</div>
            <ul role="list" class="-mx-2 space-y-1">
                <x-forms.list-item route="/shop" title="Zum Seelenfunke Shop" pageName="shop" icon="shopping-bag"/>
            </ul>
        </li>
    @else
        <li>
            <div class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-3 ml-2 mt-2">Arbeitsbereich</div>
            <ul role="list" class="-mx-2 space-y-1">
                <x-forms.list-item route="/employee/dashboard" title="Dashboard" pageName="dashboard" icon="home"/>
                <x-forms.list-item route="/employee/projects" title="Projekte" pageName="projects" icon="briefcase"/>
            </ul>
        </li>
        <li>
            <div class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-3 ml-2 mt-6">Verwaltung</div>
            <ul role="list" class="-mx-2 space-y-1">
                <x-forms.list-item route="/employee/payslips" title="Gehaltsabrechnungen" pageName="payslips" icon="document-currency-euro" />
            </ul>
        </li>
    @endif
</x-sections.vertical-nav>
