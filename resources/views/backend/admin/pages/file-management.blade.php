<x-layouts.backend_layout guard="admin">

    @section('content')

        {{-- Filemanager--}}
        @livewire("admin.file-manager")

        {{-- Directorymanager--}}
        @livewire("admin.directory-manager")

    @endsection

</x-layouts.backend_layout>
