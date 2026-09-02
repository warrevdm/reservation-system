(() => {
    const cfg = window.PASTO_ADMIN;
    if (!cfg) return;

    const state = {
        date: cfg.today,
        data: { rooms: [], tables: [], reservations: [], summary: {} },
        roomId: null,
        selectedReservationId: null,
        layoutMode: false,
        draggingReservationId: null,
    };

    const $ = (selector) => document.querySelector(selector);
    const dateInput = $('#adminDate');
    const queue = $('#reservationQueue');
    const floor = $('#floorPlan');
    const zoneTabs = $('#zoneTabs');
    const detail = $('#reservationDetail');
    const detailReference = $('#detailReference');
    const floorLabel = $('#floorLabel');
    const floorHint = $('#floorHint');
    const layoutButton = $('#layoutButton');

    const escapeHtml = (value = '') => String(value).replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
    const time = (value) => String(value || '').slice(0, 5);
    const activeStatus = (r) => !['cancelled', 'completed', 'no_show'].includes(r.status);
    const selectedReservation = () => state.data.reservations.find(r => Number(r.id) === Number(state.selectedReservationId)) || null;

    const statusLabels = {
        new: 'Nieuw', confirmed: 'Bevestigd', seated: 'Aan tafel', completed: 'Afgerond', cancelled: 'Geannuleerd', no_show: 'No-show'
    };

    async function api(action, options = {}) {
        const method = options.method || 'GET';
        const headers = { 'Accept': 'application/json', ...(options.headers || {}) };
        const request = { method, headers };
        if (options.body !== undefined) {
            headers['Content-Type'] = 'application/json';
            headers['X-CSRF-Token'] = cfg.csrf;
            request.body = JSON.stringify(options.body);
        }
        const response = await fetch(`${cfg.api}?action=${encodeURIComponent(action)}${options.query ? `&${options.query}` : ''}`, request);
        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.ok === false) throw new Error(data.message || 'Er liep iets mis.');
        return data;
    }

    function toast(message, type = '') {
        const el = document.createElement('div');
        el.className = `toast ${type}`.trim();
        el.textContent = message;
        $('#toastStack').appendChild(el);
        setTimeout(() => el.remove(), 3600);
    }

    async function loadDay({ keepSelection = true } = {}) {
        state.date = dateInput.value || cfg.today;
        queue.innerHTML = '<div class="queue-empty">Dagplanning laden…</div>';
        floor.querySelectorAll('.dining-table').forEach(el => el.remove());
        try {
            const result = await api('day', { query: `date=${encodeURIComponent(state.date)}` });
            state.data = result.data;
            if (!state.roomId || !state.data.rooms.some(room => Number(room.id) === Number(state.roomId))) {
                state.roomId = state.data.rooms[0] ? Number(state.data.rooms[0].id) : null;
            }
            if (!keepSelection || !state.data.reservations.some(r => Number(r.id) === Number(state.selectedReservationId))) {
                state.selectedReservationId = null;
            }
            renderAll();
        } catch (error) {
            queue.innerHTML = `<div class="queue-empty">${escapeHtml(error.message)}</div>`;
            toast(error.message, 'error');
        }
    }

    function renderAll() {
        renderStats();
        renderZones();
        renderQueue();
        renderFloor();
        renderDetail();
    }

    function renderStats() {
        const summary = state.data.summary || {};
        $('#statReservations').textContent = summary.reservations ?? 0;
        $('#statCovers').textContent = summary.covers ?? 0;
        $('#statUnassigned').textContent = summary.unassigned ?? 0;
        $('#statNew').textContent = summary.new ?? 0;
    }

    function reservationCard(r, compact = false) {
        const tables = (r.tables || []).map(t => t.name).join(', ');
        const el = document.createElement('div');
        el.className = `reservation-card status-${r.status}${Number(r.id) === Number(state.selectedReservationId) ? ' is-selected' : ''}`;
        el.draggable = activeStatus(r) && !state.layoutMode;
        el.dataset.reservationId = r.id;
        el.innerHTML = `
            <div class="reservation-time">${escapeHtml(time(r.start_time))}</div>
            <div class="reservation-name">${escapeHtml(r.guest_name)}</div>
            <div class="reservation-meta">
                <span class="card-badge">${Number(r.party_size)}p</span>
                <span class="card-badge">${escapeHtml(statusLabels[r.status] || r.status)}</span>
                ${tables ? `<span class="card-badge">${escapeHtml(tables)}</span>` : ''}
            </div>
        `;
        el.addEventListener('click', () => selectReservation(Number(r.id)));
        el.addEventListener('dragstart', event => {
            state.draggingReservationId = Number(r.id);
            state.selectedReservationId = Number(r.id);
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(r.id));
            requestAnimationFrame(() => { renderQueue(); renderFloor(); renderDetail(); });
        });
        el.addEventListener('dragend', () => {
            state.draggingReservationId = null;
            floor.querySelectorAll('.dining-table').forEach(table => table.classList.remove('drag-over'));
        });
        if (compact) el.style.opacity = '.92';
        return el;
    }

    function renderQueue() {
        queue.innerHTML = '';
        const reservations = [...state.data.reservations].sort((a, b) => time(a.start_time).localeCompare(time(b.start_time)));
        const unassigned = reservations.filter(r => activeStatus(r) && !(r.tables || []).length);
        const assigned = reservations.filter(r => activeStatus(r) && (r.tables || []).length);
        const history = reservations.filter(r => !activeStatus(r));

        const addHeading = (title, count) => {
            const heading = document.createElement('div');
            heading.style.cssText = 'display:flex;justify-content:space-between;padding:8px 4px 7px;color:#8d9381;font-size:9px;font-weight:700;letter-spacing:.11em;text-transform:uppercase';
            heading.innerHTML = `<span>${escapeHtml(title)}</span><span>${count}</span>`;
            queue.appendChild(heading);
        };

        addHeading('Nog in te delen', unassigned.length);
        if (!unassigned.length) {
            const empty = document.createElement('div');
            empty.className = 'queue-empty';
            empty.style.padding = '18px 10px 24px';
            empty.textContent = 'Alles is ingedeeld. Mooi zo.';
            queue.appendChild(empty);
        } else {
            unassigned.forEach(r => queue.appendChild(reservationCard(r)));
        }

        if (assigned.length) {
            addHeading('Dagplanning', assigned.length);
            assigned.forEach(r => queue.appendChild(reservationCard(r, true)));
        }

        if (history.length) {
            addHeading('Afgerond / geannuleerd', history.length);
            history.forEach(r => queue.appendChild(reservationCard(r, true)));
        }
    }

    function renderZones() {
        zoneTabs.innerHTML = '';
        state.data.rooms.forEach(room => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `zone-tab${Number(room.id) === Number(state.roomId) ? ' is-active' : ''}`;
            btn.textContent = room.name;
            btn.addEventListener('click', () => {
                state.roomId = Number(room.id);
                renderZones();
                renderFloor();
            });
            zoneTabs.appendChild(btn);
        });
        const room = state.data.rooms.find(r => Number(r.id) === Number(state.roomId));
        floorLabel.textContent = room ? room.name : 'Tafelplan';
    }

    function reservationsForTable(tableId) {
        return state.data.reservations
            .filter(r => (r.tables || []).some(t => Number(t.id) === Number(tableId)))
            .sort((a, b) => time(a.start_time).localeCompare(time(b.start_time)));
    }

    function renderFloor() {
        floor.querySelectorAll('.dining-table').forEach(el => el.remove());
        floor.classList.toggle('layout-mode', state.layoutMode);
        layoutButton.classList.toggle('is-active', state.layoutMode);
        layoutButton.textContent = state.layoutMode ? 'Indeling opslaan ✓' : 'Indeling aanpassen';
        floorHint.textContent = state.layoutMode ? 'Sleep tafels naar hun echte positie' : 'Sleep een reservatie naar de gewenste tafel';

        const selected = selectedReservation();
        const tables = state.data.tables.filter(t => Number(t.room_id) === Number(state.roomId));
        tables.forEach(table => {
            const el = document.createElement('div');
            el.className = `dining-table shape-${table.shape}`;
            if (selected && activeStatus(selected)) {
                el.classList.add('can-drop');
                if (Number(selected.party_size) > Number(table.seats)) el.classList.add('capacity-warning');
            }
            if (state.layoutMode) el.classList.add('layout-draggable');
            el.dataset.tableId = table.id;
            el.style.left = `${Number(table.pos_x)}%`;
            el.style.top = `${Number(table.pos_y)}%`;
            el.style.width = `${Number(table.width_pct)}%`;
            el.style.height = `${Number(table.height_pct)}%`;
            el.innerHTML = `<div><div class="table-name">${escapeHtml(table.name)}</div><div class="table-seats">${Number(table.seats)} plaatsen</div></div>`;

            const tableReservations = reservationsForTable(table.id);
            if (tableReservations.length) {
                const dots = document.createElement('div');
                dots.className = 'table-assignments';
                tableReservations.slice(0, 6).forEach(r => {
                    const dot = document.createElement('span');
                    dot.className = `assignment-dot${r.status === 'new' ? ' is-new' : ''}`;
                    dot.title = `${time(r.start_time)} · ${r.guest_name} · ${r.party_size}p`;
                    dots.appendChild(dot);
                });
                el.appendChild(dots);
                el.title = tableReservations.map(r => `${time(r.start_time)} ${r.guest_name} (${r.party_size}p)`).join('\n');
            }

            if (state.layoutMode) {
                makeTablePositionDraggable(el, table);
            } else {
                el.addEventListener('dragover', event => {
                    if (!state.draggingReservationId) return;
                    event.preventDefault();
                    event.dataTransfer.dropEffect = 'move';
                    el.classList.add('drag-over');
                });
                el.addEventListener('dragleave', () => el.classList.remove('drag-over'));
                el.addEventListener('drop', async event => {
                    event.preventDefault();
                    el.classList.remove('drag-over');
                    const id = Number(event.dataTransfer.getData('text/plain') || state.draggingReservationId);
                    if (id) await assignReservation(id, Number(table.id));
                });
                el.addEventListener('click', async () => {
                    const selectedNow = selectedReservation();
                    if (selectedNow && activeStatus(selectedNow)) {
                        await assignReservation(Number(selectedNow.id), Number(table.id));
                    } else if (tableReservations.length) {
                        selectReservation(Number(tableReservations[0].id));
                    }
                });
            }

            floor.appendChild(el);
        });
    }

    function makeTablePositionDraggable(el, table) {
        let start = null;
        el.addEventListener('pointerdown', event => {
            if (!state.layoutMode) return;
            event.preventDefault();
            el.setPointerCapture(event.pointerId);
            const floorRect = floor.getBoundingClientRect();
            const tableRect = el.getBoundingClientRect();
            start = {
                pointerX: event.clientX,
                pointerY: event.clientY,
                leftPx: tableRect.left - floorRect.left,
                topPx: tableRect.top - floorRect.top,
                floorRect,
            };
        });
        el.addEventListener('pointermove', event => {
            if (!start) return;
            const left = start.leftPx + (event.clientX - start.pointerX);
            const top = start.topPx + (event.clientY - start.pointerY);
            const maxLeft = Math.max(1, start.floorRect.width - el.offsetWidth);
            const maxTop = Math.max(1, start.floorRect.height - el.offsetHeight);
            const x = Math.max(0, Math.min(100, (left / start.floorRect.width) * 100));
            const y = Math.max(0, Math.min(100, (top / start.floorRect.height) * 100));
            el.style.left = `${x}%`;
            el.style.top = `${y}%`;
            el.dataset.pendingX = String(Math.min(x, (maxLeft / start.floorRect.width) * 100));
            el.dataset.pendingY = String(Math.min(y, (maxTop / start.floorRect.height) * 100));
        });
        el.addEventListener('pointerup', async event => {
            if (!start) return;
            start = null;
            const x = Number(el.dataset.pendingX ?? table.pos_x);
            const y = Number(el.dataset.pendingY ?? table.pos_y);
            table.pos_x = x;
            table.pos_y = y;
            try {
                await api('table_position', { method: 'POST', body: { table_id: Number(table.id), x, y } });
            } catch (error) {
                toast(error.message, 'error');
            }
            try { el.releasePointerCapture(event.pointerId); } catch (_) {}
        });
    }

    async function assignReservation(reservationId, tableId) {
        try {
            const result = await api('assign', { method: 'POST', body: { reservation_id: reservationId, table_id: tableId } });
            const warning = result.capacity_warning;
            state.selectedReservationId = reservationId;
            await loadDay();
            toast(warning ? `Ingedeld, maar ${result.table.name} heeft minder plaatsen dan de groep.` : `Reservatie toegewezen aan ${result.table.name}.`, warning ? 'warning' : '');
        } catch (error) {
            toast(error.message, 'error');
        }
    }

    function selectReservation(id) {
        state.selectedReservationId = id;
        renderQueue();
        renderFloor();
        renderDetail();
    }

    function renderDetail() {
        const r = selectedReservation();
        if (!r) {
            detailReference.textContent = '';
            detail.innerHTML = '<div class="detail-placeholder">Klik op een reservatie om details te bekijken, de status aan te passen of ze opnieuw in te delen.</div>';
            return;
        }
        detailReference.textContent = r.public_code || '';
        const tables = (r.tables || []).map(t => t.name).join(', ') || 'Nog niet ingedeeld';
        detail.innerHTML = `
            <div class="detail-time">${escapeHtml(time(r.start_time))} · ${escapeHtml(r.reservation_date)}</div>
            <div class="detail-name">${escapeHtml(r.guest_name)}</div>
            <div class="detail-party">${Number(r.party_size)} ${Number(r.party_size) === 1 ? 'persoon' : 'personen'} · ${escapeHtml(tables)}</div>

            <div class="detail-section">
                <div class="detail-label">Contact</div>
                <div class="detail-value">${r.guest_phone ? escapeHtml(r.guest_phone) : '—'}<br>${r.guest_email ? escapeHtml(r.guest_email) : '—'}</div>
            </div>
            <div class="detail-section">
                <div class="detail-label">Opmerking</div>
                <div class="detail-value">${r.notes ? escapeHtml(r.notes).replace(/\n/g, '<br>') : 'Geen opmerkingen'}</div>
            </div>
            <div class="detail-section">
                <div class="detail-label">Status</div>
                <div class="status-grid">
                    ${Object.entries(statusLabels).map(([key, label]) => `<button type="button" class="status-btn${r.status === key ? ' is-active' : ''}" data-status="${key}">${label}</button>`).join('')}
                </div>
            </div>
            <div class="detail-actions">
                <button type="button" class="small-btn" id="editReservation">Reservatie bewerken</button>
                ${(r.tables || []).length ? '<button type="button" class="small-btn" id="unassignReservation">Tafel loskoppelen</button>' : ''}
            </div>
        `;
        detail.querySelectorAll('[data-status]').forEach(btn => btn.addEventListener('click', () => updateStatus(Number(r.id), btn.dataset.status)));
        $('#editReservation')?.addEventListener('click', () => openReservationModal(r));
        $('#unassignReservation')?.addEventListener('click', async () => {
            try {
                await api('unassign', { method: 'POST', body: { reservation_id: Number(r.id) } });
                await loadDay();
                toast('Tafel losgekoppeld.');
            } catch (error) { toast(error.message, 'error'); }
        });
    }

    async function updateStatus(id, status) {
        try {
            await api('status', { method: 'POST', body: { reservation_id: id, status } });
            await loadDay();
            toast(`Status aangepast naar ${statusLabels[status]}.`);
        } catch (error) { toast(error.message, 'error'); }
    }

    function openModal(content) {
        const root = $('#modalRoot');
        root.innerHTML = `<div class="modal-backdrop"><div class="modal">${content}</div></div>`;
        const close = () => root.innerHTML = '';
        root.querySelector('.modal-backdrop').addEventListener('click', event => { if (event.target.classList.contains('modal-backdrop')) close(); });
        root.querySelectorAll('[data-modal-close]').forEach(btn => btn.addEventListener('click', close));
        return { root, close };
    }

    function openReservationModal(r = null) {
        const isEdit = Boolean(r);
        const modal = openModal(`
            <div class="modal-head"><div><span class="eyebrow" style="color:#a2b470;font-size:9px">${isEdit ? 'BEWERKEN' : 'NIEUW'}</span><h2>${isEdit ? 'Reservatie aanpassen' : 'Nieuwe reservatie'}</h2></div><button class="modal-close" data-modal-close>×</button></div>
            <form id="reservationModalForm">
                <div class="form-grid">
                    <div class="field-group"><label>Datum</label><input class="input" type="date" name="date" required value="${escapeHtml(r?.reservation_date || state.date)}"></div>
                    <div class="field-group"><label>Tijd</label><input class="input" type="time" name="time" required value="${escapeHtml(time(r?.start_time) || '18:00')}"></div>
                    <div class="field-group"><label>Personen</label><input class="input" type="number" min="1" max="50" name="party_size" required value="${Number(r?.party_size || 2)}"></div>
                    <div class="field-group"><label>Duur (min.)</label><input class="input" type="number" min="30" step="15" name="duration_minutes" value="${Number(r?.duration_minutes || cfg.settings.default_duration_minutes || 120)}"></div>
                    <div class="field-group field-span-2"><label>Naam</label><input class="input" name="name" required maxlength="100" value="${escapeHtml(r?.guest_name || '')}"></div>
                    <div class="field-group"><label>E-mail</label><input class="input" type="email" name="email" maxlength="190" value="${escapeHtml(r?.guest_email || '')}"></div>
                    <div class="field-group"><label>Telefoon</label><input class="input" name="phone" maxlength="40" value="${escapeHtml(r?.guest_phone || '')}"></div>
                    <div class="field-group field-span-2"><label>Opmerking</label><textarea class="input textarea" name="notes">${escapeHtml(r?.notes || '')}</textarea></div>
                </div>
                <div class="modal-actions"><button type="button" class="btn btn-secondary" data-modal-close>Annuleren</button><button class="btn btn-primary" type="submit">Opslaan</button></div>
            </form>
        `);
        modal.root.querySelector('#reservationModalForm').addEventListener('submit', async event => {
            event.preventDefault();
            const data = Object.fromEntries(new FormData(event.currentTarget).entries());
            if (r) data.id = Number(r.id);
            data.party_size = Number(data.party_size);
            data.duration_minutes = Number(data.duration_minutes);
            try {
                const result = await api('reservation_save', { method: 'POST', body: data });
                state.selectedReservationId = Number(result.reservation.id);
                modal.close();
                dateInput.value = data.date;
                await loadDay();
                toast(isEdit ? 'Reservatie bijgewerkt.' : 'Reservatie toegevoegd.');
            } catch (error) { toast(error.message, 'error'); }
        });
    }

    function openTableModal() {
        const roomOptions = state.data.rooms.map(room => `<option value="${room.id}"${Number(room.id) === Number(state.roomId) ? ' selected' : ''}>${escapeHtml(room.name)}</option>`).join('');
        const modal = openModal(`
            <div class="modal-head"><div><span class="eyebrow" style="color:#a2b470;font-size:9px">TAFELPLAN</span><h2>Tafel toevoegen</h2></div><button class="modal-close" data-modal-close>×</button></div>
            <form id="tableModalForm">
                <div class="form-grid">
                    <div class="field-group"><label>Zone</label><select class="input" name="room_id">${roomOptions}</select></div>
                    <div class="field-group"><label>Naam</label><input class="input" name="name" required placeholder="bv. B9"></div>
                    <div class="field-group"><label>Plaatsen</label><input class="input" type="number" name="seats" min="1" max="30" value="4" required></div>
                    <div class="field-group"><label>Vorm</label><select class="input" name="shape"><option value="round">Rond</option><option value="square">Vierkant</option><option value="rectangle">Rechthoek</option></select></div>
                </div>
                <div class="modal-actions"><button type="button" class="btn btn-secondary" data-modal-close>Annuleren</button><button class="btn btn-primary" type="submit">Tafel toevoegen</button></div>
            </form>
        `);
        modal.root.querySelector('#tableModalForm').addEventListener('submit', async event => {
            event.preventDefault();
            const data = Object.fromEntries(new FormData(event.currentTarget).entries());
            data.room_id = Number(data.room_id); data.seats = Number(data.seats);
            try {
                await api('table_save', { method: 'POST', body: data });
                state.roomId = data.room_id;
                modal.close();
                await loadDay();
                state.layoutMode = true;
                renderFloor();
                toast('Tafel toegevoegd. Sleep ze nu naar de juiste plek.');
            } catch (error) { toast(error.message, 'error'); }
        });
    }

    function openSettingsModal() {
        const s = cfg.settings;
        const dayNames = {1:'Maandag',2:'Dinsdag',3:'Woensdag',4:'Donderdag',5:'Vrijdag',6:'Zaterdag',7:'Zondag'};
        const hours = s.opening_hours || {};
        const rows = Object.entries(dayNames).map(([day, name]) => {
            const h = hours[day] || {};
            return `<div style="display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:8px;align-items:center;margin-bottom:8px"><strong style="font-size:11px;color:#384510">${name}</strong><input class="input" style="min-height:42px" type="time" name="open_${day}" value="${escapeHtml(h.open || '')}"><input class="input" style="min-height:42px" type="time" name="close_${day}" value="${escapeHtml(h.close || '')}"></div>`;
        }).join('');
        const modal = openModal(`
            <div class="modal-head"><div><span class="eyebrow" style="color:#a2b470;font-size:9px">ONLINE RESERVEREN</span><h2>Instellingen</h2></div><button class="modal-close" data-modal-close>×</button></div>
            <form id="settingsForm">
                <div class="form-grid">
                    <div class="field-group"><label>Interval (min.)</label><input class="input" type="number" min="15" step="15" name="booking_interval_minutes" value="${Number(s.booking_interval_minutes || 30)}"></div>
                    <div class="field-group"><label>Standaard duur (min.)</label><input class="input" type="number" min="30" step="15" name="default_duration_minutes" value="${Number(s.default_duration_minutes || 120)}"></div>
                    <div class="field-group"><label>Max. online groep</label><input class="input" type="number" min="1" max="50" name="max_online_party_size" value="${Number(s.max_online_party_size || 12)}"></div>
                    <div class="field-group"><label>Max. covers per tijdslot</label><input class="input" type="number" min="1" name="max_covers_per_slot" value="${Number(s.max_covers_per_slot || 80)}"></div>
                    <div class="field-group"><label>Min. vooraf boeken (min.)</label><input class="input" type="number" min="0" step="15" name="min_lead_minutes" value="${Number(s.min_lead_minutes || 60)}"></div>
                    <div class="field-group"><label>Dagen vooruit</label><input class="input" type="number" min="1" name="bookable_days_ahead" value="${Number(s.bookable_days_ahead || 90)}"></div>
                </div>
                <div class="detail-section" style="margin-top:5px"><div class="detail-label" style="margin-bottom:12px">Openingsuren · laat beide velden leeg voor gesloten</div>${rows}</div>
                <div class="modal-actions"><button type="button" class="btn btn-secondary" data-modal-close>Annuleren</button><button class="btn btn-primary" type="submit">Instellingen opslaan</button></div>
            </form>
        `);
        modal.root.querySelector('#settingsForm').addEventListener('submit', async event => {
            event.preventDefault();
            const raw = Object.fromEntries(new FormData(event.currentTarget).entries());
            const openingHours = {};
            for (let day = 1; day <= 7; day++) {
                const open = raw[`open_${day}`] || '';
                const close = raw[`close_${day}`] || '';
                if (open && close) openingHours[String(day)] = { open, close };
                delete raw[`open_${day}`]; delete raw[`close_${day}`];
            }
            const numericKeys = ['booking_interval_minutes','default_duration_minutes','max_online_party_size','max_covers_per_slot','min_lead_minutes','bookable_days_ahead'];
            numericKeys.forEach(key => raw[key] = Number(raw[key]));
            raw.opening_hours = openingHours;
            try {
                await api('settings_save', { method: 'POST', body: raw });
                cfg.settings = { ...cfg.settings, ...raw };
                modal.close();
                toast('Instellingen opgeslagen.');
            } catch (error) { toast(error.message, 'error'); }
        });
    }

    dateInput.addEventListener('change', () => loadDay({ keepSelection: false }));
    $('#todayButton').addEventListener('click', () => { dateInput.value = cfg.today; loadDay({ keepSelection: false }); });
    layoutButton.addEventListener('click', () => { state.layoutMode = !state.layoutMode; renderFloor(); renderQueue(); });
    $('#addTableButton').addEventListener('click', openTableModal);
    $('#newReservationNav').addEventListener('click', () => openReservationModal());
    $('#settingsNav').addEventListener('click', openSettingsModal);

    loadDay({ keepSelection: false });
})();
