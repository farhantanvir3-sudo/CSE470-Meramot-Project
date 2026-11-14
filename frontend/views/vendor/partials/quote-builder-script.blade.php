@once
<style>[x-cloak] { display: none !important; }</style>
<script>
(function () {
    if (window.meramotBidQuoteForm && window.meramotJobQuoteForm) {
        return;
    }

    function parseAmount(value) {
        const parsed = parseFloat(value);
        return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
    }

    function roundMoney(value) {
        return Math.round((Number(value) || 0) * 100) / 100;
    }

    function formatBdt(value) {
        return Number(roundMoney(value)).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function sumParts(parts) {
        return roundMoney((parts || []).reduce(function (sum, item) {
            return sum + parseAmount(item ? item.cost : 0);
        }, 0));
    }

    function removePartRow(parts, index) {
        if (parts.length > 1) {
            parts.splice(index, 1);
        } else {
            parts[0] = { name: '', cost: '' };
        }
    }

    function formatInputNumber(value) {
        if (value === '' || value === null || value === undefined) {
            return '';
        }
        const num = Number(value);
        return Number.isFinite(num) ? String(roundMoney(num)) : '';
    }

    function normalizeRows(rawList, minRows) {
        const rows = Array.isArray(rawList)
            ? rawList.map(function (item) {
                return {
                    name: item && item.name ? String(item.name) : '',
                    cost: formatInputNumber(item ? item.cost : '')
                };
            })
            : [];

        while (rows.length < (minRows || 1)) {
            rows.push({ name: '', cost: '' });
        }

        return rows;
    }

    window.meramotBidQuoteForm = function (config) {
        const cfg = config || {};
        const bidsByShop = cfg.existingBidsByShop || {};
        const fallbackShopId = cfg.initialShopId
            ? String(cfg.initialShopId)
            : (Object.keys(bidsByShop)[0] || '');

        return {
            selectedShopId: fallbackShopId,
            existingBidsByShop: bidsByShop,
            parts: [{ name: '', cost: '' }],
            laborCost: '',
            estimatedDays: 2,
            hasWarranty: true,
            warrantyDays: 90,
            technicianNote: '',
            editing: false,

            init: function () {
                this.syncForShop(false);
            },

            addPart: function () {
                this.parts.push({ name: '', cost: '' });
            },

            removePart: function (index) {
                removePartRow(this.parts, index);
            },

            formatMoney: formatBdt,

            get partsSubtotal() {
                return sumParts(this.parts);
            },

            get existingBid() {
                if (this.selectedShopId && this.existingBidsByShop[this.selectedShopId]) {
                    return this.existingBidsByShop[this.selectedShopId];
                }
                return null;
            },

            get totalEstimate() {
                return roundMoney(this.partsSubtotal + parseAmount(this.laborCost));
            },

            syncForShop: function (keepEditing) {
                const bid = this.existingBid;
                if (bid) {
                    this.parts = normalizeRows(bid.parts_items, 1);
                    this.laborCost = formatInputNumber(bid.labor_cost);
                    this.estimatedDays = Number(bid.estimated_days) || 2;
                    this.hasWarranty = Boolean(bid.has_warranty);
                    this.warrantyDays = Number(bid.warranty_days) > 0 ? Number(bid.warranty_days) : 90;
                    this.technicianNote = bid.technician_note ? String(bid.technician_note) : '';
                    if (!keepEditing) {
                        this.editing = false;
                    }
                } else {
                    this.parts = [{ name: '', cost: '' }, { name: '', cost: '' }];
                    this.laborCost = '';
                    this.estimatedDays = 2;
                    this.hasWarranty = true;
                    this.warrantyDays = 90;
                    this.technicianNote = '';
                    this.editing = true;
                }
            },

            startEdit: function () {
                this.syncForShop(true);
                this.editing = true;
            },

            cancelEdit: function () {
                this.syncForShop(false);
            }
        };
    };

    window.meramotJobQuoteForm = function (config) {
        const cfg = config || {};
        const saved = cfg.savedQuote || {};

        return {
            chosenStatus: cfg.initialStatus || 'AT_SHOP',
            savedQuote: saved,
            editingQuote: Boolean(cfg.openInitially),
            parts: normalizeRows(saved.parts_items, 1),
            laborCost: formatInputNumber(saved.labor_cost),
            hasWarranty: saved.has_warranty !== undefined ? Boolean(saved.has_warranty) : true,
            warrantyDays: Number(saved.warranty_days) > 0 ? Number(saved.warranty_days) : 90,
            diagnosisNotes: saved.diagnosis_notes ? String(saved.diagnosis_notes) : '',

            addPart: function () {
                this.parts.push({ name: '', cost: '' });
            },

            removePart: function (index) {
                removePartRow(this.parts, index);
            },

            formatMoney: formatBdt,

            get partsSubtotal() {
                return sumParts(this.parts);
            },

            get showQuoteBuilder() {
                return this.chosenStatus === 'WAITING_APPROVAL' || this.editingQuote;
            },

            get totalQuote() {
                return roundMoney(this.partsSubtotal + parseAmount(this.laborCost));
            },

            toggleQuoteEditor: function () {
                if (this.editingQuote) {
                    this.resetToSaved();
                    this.editingQuote = false;
                } else {
                    this.resetToSaved();
                    this.editingQuote = true;
                }
            },

            resetToSaved: function () {
                this.parts = normalizeRows(this.savedQuote.parts_items, 1);
                this.laborCost = formatInputNumber(this.savedQuote.labor_cost);
                this.hasWarranty = this.savedQuote.has_warranty !== undefined ? Boolean(this.savedQuote.has_warranty) : true;
                this.warrantyDays = Number(this.savedQuote.warranty_days) > 0 ? Number(this.savedQuote.warranty_days) : 90;
                this.diagnosisNotes = this.savedQuote.diagnosis_notes ? String(this.savedQuote.diagnosis_notes) : '';
            }
        };
    };
})();
</script>
@endonce
