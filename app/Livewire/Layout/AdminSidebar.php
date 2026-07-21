<?php

namespace App\Livewire\Layout;

use App\Livewire\Actions\Logout;
use App\Models\SidebarMenuItem;
use Livewire\Attributes\On;
use Livewire\Component;

class AdminSidebar extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirectRoute('login', navigate: false);
    }

    #[On('sidebar-menu-updated')]
    public function refreshMenu(): void
    {
        // El evento fuerza un nuevo render con la jerarquía persistida.
    }

    public function render()
    {
        return view('livewire.layout.admin-sidebar', [
            'sidebarItems' => SidebarMenuItem::visibleTreeFor(auth()->user()),
        ]);
    }
}
