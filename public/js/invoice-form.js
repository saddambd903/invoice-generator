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
    let vendors = [];
    try { vendors = JSON.parse(document.getElementById('vendor-data')?.textContent || '[]'); } catch (e) { vendors = []; }
    const byName = (name) => vendors.find((v) => v.name.toLowerCase() === name.trim().toLowerCase());
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

    /** Fill the contact fields from a saved vendor; on a new invoice also copy their last invoice. */
    function chooseVendor(v) {
        nameInput.value = v.name;
        $('#vendor_phone').value = v.phone || '';
        $('#vendor_email').value = v.email || '';
        $('#vendor_address').value = v.address || '';
        if (editing) return; // never overwrite the items of an invoice being edited
        if (v.uid === lastLookup) return;
        lastLookup = v.uid;
        autofill({ id: v.uid });
    }

    // Typing an existing vendor's full name and leaving the field also picks them.
    nameInput.addEventListener('change', () => {
        const v = byName(nameInput.value);
        if (v) chooseVendor(v);
    });

    /* ---- vendor picker (custom dropdown) ---- */
    const combo = document.getElementById('vendor-combo');
    const panel = document.getElementById('vendor-panel');
    const list = document.getElementById('vendor-options');
    const count = document.getElementById('vendor-count');
    let shown = [];      // [{vendor} | {create: name}]
    let active = -1;

    function highlight(text, q) {
        const frag = document.createDocumentFragment();
        const i = q ? text.toLowerCase().indexOf(q.toLowerCase()) : -1;
        if (i < 0) { frag.append(text); return frag; }
        const mark = document.createElement('mark');
        mark.textContent = text.slice(i, i + q.length);
        frag.append(text.slice(0, i), mark, text.slice(i + q.length));
        return frag;
    }

    function render() {
        const q = nameInput.value.trim();
        const ql = q.toLowerCase();
        const matches = vendors.filter((v) => !ql
            || v.name.toLowerCase().includes(ql)
            || (v.phone || '').toLowerCase().includes(ql)
            || (v.email || '').toLowerCase().includes(ql));
        // Names starting with the search come first.
        matches.sort((a, b) => (b.name.toLowerCase().startsWith(ql)) - (a.name.toLowerCase().startsWith(ql)) || a.name.localeCompare(b.name));

        shown = matches.map((v) => ({ vendor: v }));
        if (q && !byName(q)) shown.push({ create: q });

        list.innerHTML = '';
        if (!vendors.length && !q) {
            const li = document.createElement('li');
            li.className = 'combo-empty';
            li.textContent = 'No saved vendors yet. Type a name to add your first one.';
            list.append(li);
        } else if (!shown.length) {
            const li = document.createElement('li');
            li.className = 'combo-empty';
            li.textContent = 'No vendors match.';
            list.append(li);
        }

        shown.forEach((item, i) => {
            const li = document.createElement('li');
            li.id = 'vendor-opt-' + i;
            li.setAttribute('role', 'option');
            li.className = 'combo-opt';
            if (item.vendor) {
                const v = item.vendor;
                const av = document.createElement('span');
                av.className = 'combo-av';
                av.textContent = v.name.charAt(0).toUpperCase();
                const txt = document.createElement('span');
                txt.className = 'combo-txt';
                const nm = document.createElement('span');
                nm.className = 'combo-name';
                nm.append(highlight(v.name, q));
                const sub = document.createElement('span');
                sub.className = 'combo-sub';
                const subText = [v.phone, v.email].filter(Boolean).join(' · ');
                sub.append(subText ? highlight(subText, q) : 'No contact saved');
                txt.append(nm, sub);
                li.append(av, txt);
                if (nameInput.value.trim().toLowerCase() === v.name.toLowerCase()) li.classList.add('current');
            } else {
                li.classList.add('combo-new');
                const av = document.createElement('span');
                av.className = 'combo-av plus';
                av.textContent = '+';
                const txt = document.createElement('span');
                txt.className = 'combo-txt';
                const nm = document.createElement('span');
                nm.className = 'combo-name';
                nm.textContent = 'New vendor: “' + item.create + '”';
                const sub = document.createElement('span');
                sub.className = 'combo-sub';
                sub.textContent = 'Added when you save the invoice';
                txt.append(nm, sub);
                li.append(av, txt);
            }
            li.addEventListener('mousedown', (e) => { e.preventDefault(); pick(i); });
            li.addEventListener('mousemove', () => setActive(i, false));
            list.append(li);
        });

        count.textContent = vendors.length
            ? (q ? `${matches.length} of ${vendors.length} saved vendors` : `${vendors.length} saved ${vendors.length === 1 ? 'vendor' : 'vendors'}`)
            : 'Saved vendors';
        setActive(shown.length ? 0 : -1, false);
    }

    function setActive(i, scroll = true) {
        active = i;
        [...list.querySelectorAll('.combo-opt')].forEach((li, n) => li.classList.toggle('active', n === i));
        const el = document.getElementById('vendor-opt-' + i);
        if (el) {
            nameInput.setAttribute('aria-activedescendant', el.id);
            if (scroll) el.scrollIntoView({ block: 'nearest' });
        } else {
            nameInput.removeAttribute('aria-activedescendant');
        }
    }

    function open() {
        render();
        panel.hidden = false;
        combo.classList.add('open');
        nameInput.setAttribute('aria-expanded', 'true');
    }
    function close() {
        panel.hidden = true;
        combo.classList.remove('open');
        nameInput.setAttribute('aria-expanded', 'false');
        nameInput.removeAttribute('aria-activedescendant');
    }

    function pick(i) {
        const item = shown[i];
        if (!item) return;
        if (item.vendor) {
            chooseVendor(item.vendor);
        } else {
            nameInput.value = item.create;
            note.hidden = true;
            $('#vendor_phone').focus();
        }
        close();
    }

    nameInput.addEventListener('focus', open);
    nameInput.addEventListener('input', open);
    nameInput.addEventListener('blur', () => setTimeout(close, 120));
    nameInput.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (panel.hidden) open(); else setActive(Math.min(active + 1, shown.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive(Math.max(active - 1, 0));
        } else if (e.key === 'Enter' && !panel.hidden && active >= 0) {
            e.preventDefault(); // pick instead of submitting the form
            pick(active);
        } else if (e.key === 'Escape' && !panel.hidden) {
            e.preventDefault();
            close();
        } else if (e.key === 'Tab') {
            close();
        }
    });
    document.getElementById('vendor-toggle').addEventListener('mousedown', (e) => {
        e.preventDefault();
        if (panel.hidden) { nameInput.focus(); open(); } else close();
    });

    /* ---- boot ---- */
    renumber();
    applyType();
    applyCurrency();
    calc();
    if (form.dataset.prefillVendor) autofill({ id: form.dataset.prefillVendor });
})();
