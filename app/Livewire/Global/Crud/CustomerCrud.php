<?php

namespace App\Livewire\Global\Crud;

class CustomerCrud extends UniversalCrud
{
    public function mount(string $configClass = null): void
    {
        parent::mount('crud\\CustomerConfig');
    }
}
