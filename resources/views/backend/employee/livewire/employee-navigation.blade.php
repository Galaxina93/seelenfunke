{{-- FILE: resources\views\backend\employee\livewire\employee-navigation.blade.php --}}
<x-sections.vertical-nav>
    <li>
        <div class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-3 ml-2 mt-2">Arbeitsbereich</div>
        <ul role="list" class="-mx-2 space-y-1">
            <x-forms.list-item route="/employee/dashboard" title="Dashboard" pageName="dashboard" icon="home"/>
            <x-forms.list-item route="/employee/projects" title="Projekte" pageName="projects" icon="briefcase"/>
        </ul>
    </li>
    <li>
        <div class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-3 ml-2 mt-6">System</div>
        <ul role="list" class="-mx-2 space-y-1">
            <x-forms.list-item route="/employee/profile" title="Mein Profil" pageName="profile" icon="user-circle" />
        </ul>
    </li>
</x-sections.vertical-nav>
