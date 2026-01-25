<?php

namespace App\Livewire\Global\Crud;

class EmployeeCrud extends UniversalCrud
{
    public function mount(string $configClass = null): void
    {
        parent::mount('crud\\EmployeeConfig');
    }
}
