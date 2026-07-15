<?php

namespace App\Livewire\Ui;

use Livewire\Attributes\On;
use Livewire\Component;

class ConfirmModal extends Component
{
    public bool   $show        = false;
    public string $type        = 'danger';   // danger | warning | info | success
    public string $title       = '';
    public string $message     = '';
    public string $confirmText = 'Confirmar';
    public string $cancelText  = 'Cancelar';
    public string $action      = '';
    public array  $params      = [];

    #[On('open-confirm')]
    public function open(
        string $type,
        string $title,
        string $message,
        string $action,
        array  $params      = [],
        string $confirmText = 'Confirmar',
        string $cancelText  = 'Cancelar',
    ): void {
        $this->type        = $type;
        $this->title       = $title;
        $this->message     = $message;
        $this->action      = $action;
        $this->params      = $params;
        $this->confirmText = $confirmText;
        $this->cancelText  = $cancelText;
        $this->show        = true;
    }

    public function confirm(): void
    {
        $this->dispatch('modal-confirmed', action: $this->action, params: $this->params);
        $this->show = false;
    }

    public function cancel(): void
    {
        $this->show = false;
    }

    public function render()
    {
        return view('livewire.ui.confirm-modal');
    }
}
