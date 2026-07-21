<div id="posTicketModal" class="pos-modal-wrap pos-ticket-modal">
    <div class="pos-modal pos-modal-modern pos-ticket-preview" data-ui="xui-1iwymxt">
        <div class="modal-header-pos pos-modal-modern__header" data-ui="xui-1138a9d">
            <i class="bx bx-receipt" data-ui="xui-1ewflap"></i>
            <h4 data-ui="xui-ckcaff">Ticket</h4>
            <div data-ui="xui-rg0c4e">
                <button type="button" id="tab-cliente" onclick="posTicketTab('cliente')"
                        class="pos-btn pos-btn-sm pos-btn-primary">
                    <i class="bx bx-user"></i> Cliente
                </button>
                <button type="button" id="tab-cocina" onclick="posTicketTab('cocina')"
                        class="pos-btn pos-btn-sm pos-btn-secondary">
                    <i class="bx bx-dish"></i> Cocina
                </button>
            </div>
            <button type="button" onclick="posTicketClose()" class="pos-btn pos-btn-ghost pos-btn-sm">
                <i class="bx bx-x" data-ui="xui-miwya2"></i>
            </button>
        </div>

        <div id="pane-cliente" class="modal-body-pos pos-ticket-pane" data-ui="xui-1uykvw">
            <iframe id="iframe-cliente" data-ui="xui-1e59nxt" srcdoc=""></iframe>
        </div>
        <div id="pane-cocina" class="modal-body-pos pos-ticket-pane is-hidden">
            <iframe id="iframe-cocina" data-ui="xui-1e59nxt" srcdoc=""></iframe>
        </div>

        <div class="modal-footer-pos pos-modal-modern__footer">
            <button type="button" onclick="posTicketClose()" class="pos-btn pos-btn-ghost">Cerrar</button>
            <span data-ui="xui-ckcaff"></span>
            <button type="button" id="btn-print-active" onclick="posTicketPrint()" class="pos-btn pos-btn-primary pos-btn-lg">
                <i class="bx bx-printer"></i> Imprimir
            </button>
        </div>
    </div>
</div>
