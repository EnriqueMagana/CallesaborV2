<div id="posTicketModal" class="pos-modal-wrap" style="z-index:1500;display:none">
    <div class="pos-modal" style="max-width:500px">
        <div class="modal-header-pos" style="gap:0;padding:10px 16px">
            <i class="bx bx-receipt" style="font-size:1.1rem;color:var(--pos-accent);margin-right:8px"></i>
            <h4 style="flex:1">Ticket</h4>
            <div style="display:flex;gap:4px;margin-right:12px">
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
                <i class="bx bx-x" style="font-size:1.1rem"></i>
            </button>
        </div>

        <div id="pane-cliente" class="modal-body-pos" style="padding:0">
            <iframe id="iframe-cliente" style="width:100%;height:500px;border:none" srcdoc=""></iframe>
        </div>
        <div id="pane-cocina" class="modal-body-pos" style="padding:0;display:none">
            <iframe id="iframe-cocina" style="width:100%;height:500px;border:none" srcdoc=""></iframe>
        </div>

        <div class="modal-footer-pos">
            <button type="button" onclick="posTicketClose()" class="pos-btn pos-btn-ghost">Cerrar</button>
            <span style="flex:1"></span>
            <button type="button" id="btn-print-active" onclick="posTicketPrint()" class="pos-btn pos-btn-primary pos-btn-lg">
                <i class="bx bx-printer"></i> Imprimir
            </button>
        </div>
    </div>
</div>
