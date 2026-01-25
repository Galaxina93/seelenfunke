<?php

namespace App\Livewire\Global\Crud;

class AdminProfileCrud extends UniversalCrud
{
    public function mount(string $configClass = null): void
    {
        parent::mount('crud\\AdminProfileConfig');
    }
}
