/* Invoice form: rows, invoice type, currency, live totals and vendor auto-fill. */
(function () {
    const form = document.getElementById('invoice-form');
    if (!form) return;

    const $ = (s) => form.querySelector(s);
    const body = document.getElementById('items-body');
    const tpl = document.getElementById('row-template');
    const symbols = JSON.parse(form.dataset.symbols || '{}');
    const editing = form.dataset.editing === '1';
    const num = (v) => { const n = parseFloat(v); return isFinite(n) ? n : 0; };
    const fmt = (n) => (Math.round(n * 100) / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const type = () => form.querySelector('input[name="type"]:checked')?.value || 'onetime';

    /* ---- rows ---- */
    function renumber() {
        [...body.rows].forEach((tr, i) => {
            tr.querySelector('.sl').textContent = i + 1;
            tr.querySelectorAll('input').forEach((inp) => {
                const field = inp.dataset.field || inp.name.match(/\[(\w+)\]$/)?.[1];
                inp.dataset.field = field;
                inp.name = `items[${i}][${field}]`;
            });
        });
    }

    function addRow(data = {}) {
        const tr = tpl.content.firstElementChild.cloneNode(true);
        if (data.description != null) tr.querySelector('[data-field="description"]').value = data.description;
        if (data.quantity != null) tr.querySelector('[data-field="quantity"]').value = data.quantity;
        const unit = data.unit_price != null ? data.unit_price : data.amount; // older data only had a line amount
        if (unit != null) tr.querySelector('[data-field="unit_price"]').value = unit;
        body.appendChild(tr);
        renumber();
        applyType();
        return tr;
    }

    function setRows(rows) {
        body.innerHTML = '';
        (rows.length ? rows : [{}]).forEach((r) => addRow(r));
        calc();
    }

    body.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-remove]');
        if (!btn) return;
        btn.closest('tr').remove();
        if (!body.rows.length) addRow();
        renumber();
        calc();
    });
    body.addEventListener('input', calc);
    document.getElementById('add-row').addEventListener('click', () => {
        addRow().querySelector('[data-field="description"]').focus();
    });

    /* ---- type ---- */
    function applyType() {
        const monthly = type() === 'monthly';
        // Monthly invoices have no "Before pay": hide the row (the server saves it as 0).
        $('#row-before').hidden = monthly;
        calc();
        document.getElementById('type-hint').textContent = monthly
            ? 'Recurring bill for one billing month.'
            : 'Itemised bill with quantity per line.';
        document.getElementById('items-sub').textContent = monthly
            ? 'Services billed for the month: quantity × unit cost'
            : 'Each line: quantity × unit cost';
    }
    form.querySelectorAll('input[name="type"]').forEach((r) => r.addEventListener('change', applyType));

    /* ---- currency ---- */
    function applyCurrency() {
        const other = $('#currency').value === 'OTHER';
        $('#wrap-curother').hidden = !other;
        $('#currency_other').disabled = !other;
        calc();
    }
    $('#currency').addEventListener('change', applyCurrency);
    $('#currency_other').addEventListener('input', calc);

    function setCurrency(code) {
        if (symbols[code]) {
            $('#currency').value = code;
        } else {
            $('#currency').value = 'OTHER';
            $('#currency_other').value = code || '';
        }
        applyCurrency();
    }

    /* ---- totals ---- */
    function symbol() {
        const c = $('#currency').value;
        return c === 'OTHER' ? (($('#currency_other').value || '').toUpperCase() + ' ') : (symbols[c] || '');
    }
    function calc() {
        // Each line: Total = Quantity × Unit cost. Invoice total = sum of the lines.
        let total = 0;
        [...body.rows].forEach((tr) => {
            const qty = num(tr.querySelector('[data-field="quantity"]')?.value);
            const unit = num(tr.querySelector('[data-field="unit_price"]')?.value);
            const line = Math.round(qty * unit * 100) / 100;
            total += line;
            const cell = tr.querySelector('[data-total]');
            if (cell) cell.textContent = fmt(line);
        });
        const before = type() === 'monthly' ? 0 : num($('#before_pay').value);
        const due = total - before - num($('#now_pay').value);
        document.getElementById('o-total').textContent = symbol() + fmt(total);
        document.getElementById('o-due').textContent = symbol() + fmt(due);
        document.getElementById('due-row').classList.toggle('owed', due > 0.004);
    }
    ['#before_pay', '#now_pay'].forEach((s) => $(s).addEventListener('input', calc));

    /* ---- vendor auto-fill ---- */
    const note = document.getElementById('autofill-note');
    const nameInput = $('#vendor_name');
    const known = new Set([...document.querySelectorAll('#vendor-list option')].map((o) => o.value));
    let lastLookup = '';

    async function autofill(params) {
        const url = new URL(form.dataset.lookupUrl, window.location.origin);
        Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
        let data;
        try {
            const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) return;
            data = await res.json();
        } catch (e) { return; }
        if (!data.found) return;

        const v = data.vendor;
        nameInput.value = v.name;
        $('#vendor_phone').value = v.phone || '';
        $('#vendor_email').value = v.email || '';
        $('#vendor_address').value = v.address || '';

        let msg = `Filled in ${v.name}'s saved details.`;
        const last = data.last;
        if (last) {
            form.querySelector(`input[name="type"][value="${last.type}"]`).checked = true;
            setCurrency(last.currency);
            setRows(last.items);
            $('#comment').value = last.comment || '';
            $('#before_pay').value = 0;
            $('#now_pay').value = 0;
            if (last.type === 'monthly') $('#billing_month').value = last.next_billing_month;
            applyType();
            calc();
            msg += ` Items, currency and comment copied from ${last.number}.`;
            if (last.due > 0.004) msg += ` That invoice still has ${last.due_label} due.`;
        }
        note.textContent = msg;
        note.hidden = false;
    }

    function maybeAutofill() {
        if (editing) return; // never overwrite an invoice being edited
        const name = nameInput.value.trim();
        if (!name || name === lastLookup || !known.has(name)) return;
        lastLookup = name;
        autofill({ name });
    }
    nameInput.addEventListener('change', maybeAutofill);
    nameInput.addEventListener('input', maybeAutofill); // datalist pick fires input with the full name

    /* ---- boot ---- */
    renumber();
    applyType();
    applyCurrency();
    calc();
    if (form.dataset.prefillVendor) autofill({ id: form.dataset.prefillVendor });
})();
