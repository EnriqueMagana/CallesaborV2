<x-ticket-preview-modal id="posTicketModal" title="Ticket" initial-tab="cliente" :wire-ignore="true"
    :tabs="[
        ['key' => 'cliente', 'label' => 'Cliente', 'icon' => 'bx-user', 'title' => 'Vista previa del ticket del cliente'],
        ['key' => 'cocina', 'label' => 'Cocina', 'icon' => 'bx-dish', 'title' => 'Vista previa del ticket de cocina'],
    ]" />
