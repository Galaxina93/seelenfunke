<?php

namespace App\Livewire\Global\Widgets;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Employee;
use Livewire\Component;

class LoginLog extends Component
{
    protected array $last_logins = [];

    public function refresh(): void
    {
        $adminLogins = Admin::with('profile')->get()->map(function ($admin) {
            return [
                'first_name' => $admin->first_name,
                'last_name' => $admin->last_name,
                'last_seen' => $admin->profile->last_seen,
            ];
        });

        $customerLogins = Customer::with('profile')->get()->map(function ($customer) {
            return [
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'last_seen' => $customer->profile->last_seen,
            ];
        });

        $employeeLogins = Employee::with('profile')->get()->map(function ($employee) {
            return [
                'type'       => 'Employee',
                'first_name' => $employee->first_name,
                'last_name'  => $employee->last_name,
                'last_seen'  => optional($employee->profile)->last_seen,
            ];
        });


        $this->last_logins = $adminLogins
            ->merge($customerLogins)
            ->merge($employeeLogins)
            ->sortByDesc('last_seen')
            ->values()
            ->all();
    }

    public function render()
    {
        $this->refresh();

        return view('livewire.widgets.login-log', [
            'last_logins' => $this->last_logins
        ]);
    }
}
