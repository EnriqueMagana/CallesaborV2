<?php

namespace App\Livewire\Layout;

use App\Livewire\Actions\Logout;
use Livewire\Component;

class AdminNavbar extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirectRoute('login', navigate: false);
    }

    public function render()
    {
        return view('livewire.layout.admin-navbar');
    }
}
