(() => {
    const form = document.getElementById('bookingForm');
    if (!form) return;

    const api = window.PASTO_API || '/api.php';
    const recaptcha = window.PASTO_RECAPTCHA || { enabled: false, mode: 'v2' };
    const dateInput = document.getElementById('reservationDate');
    const partyInput = document.getElementById('partySize');
    const timeInput = document.getElementById('reservationTime');
    const shortcuts = document.getElementById('dateShortcuts');
    const partyGrid = document.getElementById('partySizeButtons');
    const timeGrid = document.getElementById('timeSlots');
    const slotState = document.getElementById('slotState');
    const slotSummary = document.getElementById('slotSummary');
    const finalSummary = document.getElementById('finalSummary');
    const toDetails = document.getElementById('toDetails');
    const submitButton = document.getElementById('submitBooking');
    const formError = document.getElementById('formError');
    const success = document.getElementById('bookingSuccess');
    const maxParty = Number(form.dataset.maxParty || 12);
    const daysAhead = Number(form.dataset.daysAhead || 90);
    let currentStep = 1;

    const iso = (date) => {
        const d = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
        return d.toISOString().slice(0, 10);
    };

    const formatDate = (value, long = true) => {
        const date = new Date(`${value}T12:00:00`);
        return new Intl.DateTimeFormat('nl-BE', long
            ? { weekday: 'long', day: 'numeric', month: 'long' }
            : { weekday: 'short', day: 'numeric', month: 'short' }
        ).format(date);
    };

    const setStep = (step) => {
        currentStep = step;
        document.querySelectorAll('.booking-step').forEach(el => el.classList.toggle('is-active', Number(el.dataset.step) === step));
        document.querySelectorAll('.progress-dot').forEach(dot => {
            const n = Number(dot.dataset.progress);
            dot.classList.toggle('is-active', n === step);
            dot.classList.toggle('is-done', n < step);
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const renderDates = () => {
        const today = new Date();
        const max = new Date();
        max.setDate(max.getDate() + daysAhead);
        dateInput.min = iso(today);
        dateInput.max = iso(max);
        dateInput.value = dateInput.value || iso(today);

        shortcuts.innerHTML = '';
        for (let i = 0; i < 4; i++) {
            const date = new Date();
            date.setDate(date.getDate() + i);
            const value = iso(date);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'date-pill';
            button.dataset.date = value;
            button.innerHTML = `<strong>${i === 0 ? 'Vandaag' : i === 1 ? 'Morgen' : formatDate(value, false).split(' ')[0]}</strong><span>${formatDate(value, false).replace(/^\S+\s/, '')}</span>`;
            button.addEventListener('click', () => {
                dateInput.value = value;
                syncDateSelection();
            });
            shortcuts.appendChild(button);
        }
        syncDateSelection();
    };

    const syncDateSelection = () => {
        shortcuts.querySelectorAll('.date-pill').forEach(button => button.classList.toggle('is-selected', button.dataset.date === dateInput.value));
        timeInput.value = '';
        toDetails.disabled = true;
    };

    const renderParty = () => {
        partyGrid.innerHTML = '';
        const directButtons = Math.min(maxParty, 8);
        for (let n = 1; n <= directButtons; n++) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'party-button';
            button.textContent = String(n);
            button.dataset.party = String(n);
            button.addEventListener('click', () => setParty(n));
            partyGrid.appendChild(button);
        }
        if (maxParty > directButtons) {
            const more = document.createElement('button');
            more.type = 'button';
            more.className = 'party-button';
            more.textContent = `${directButtons + 1}+`;
            more.dataset.party = 'more';
            more.addEventListener('click', () => {
                const answer = Number(prompt(`Met hoeveel personen kom je? (max. ${maxParty})`, String(Math.min(10, maxParty))));
                if (answer >= 1 && answer <= maxParty) setParty(answer);
            });
            partyGrid.appendChild(more);
        }
        setParty(Number(partyInput.value || 2));
    };

    const setParty = (n) => {
        partyInput.value = String(n);
        partyGrid.querySelectorAll('.party-button').forEach(button => {
            const value = button.dataset.party === 'more' ? null : Number(button.dataset.party);
            button.classList.toggle('is-selected', value === n || (button.dataset.party === 'more' && n > 8));
        });
        timeInput.value = '';
        toDetails.disabled = true;
    };

    const showSlotState = (html, loading = false) => {
        slotState.hidden = false;
        slotState.style.display = 'flex';
        slotState.classList.toggle('is-loading', loading);
        slotState.innerHTML = html;
    };

    const hideSlotState = () => {
        slotState.classList.remove('is-loading');
        slotState.hidden = true;
        slotState.style.display = 'none';
        slotState.innerHTML = '';
    };

    const fetchSlots = async () => {
        timeGrid.innerHTML = '';
        showSlotState('<span class="loader"></span><span>Even kijken wat nog vrij is…</span>', true);
        const url = `${api}?action=availability&date=${encodeURIComponent(dateInput.value)}&party_size=${encodeURIComponent(partyInput.value)}`;

        try {
            const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.message || 'Beschikbaarheid kon niet worden geladen.');

            hideSlotState();
            const party = Number(partyInput.value);
            slotSummary.textContent = `${formatDate(dateInput.value)} · ${party} ${party === 1 ? 'persoon' : 'personen'}`;

            if (data.closed || !data.slots.length) {
                timeGrid.innerHTML = '<div class="empty-slots">Op deze dag zijn er momenteel geen online reservatiemomenten beschikbaar. Kies gerust een andere datum.</div>';
                return;
            }

            data.slots.forEach(slot => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'time-button';
                button.textContent = slot.time;
                button.disabled = !slot.available;
                if (slot.available) {
                    button.addEventListener('click', () => {
                        timeInput.value = slot.time;
                        timeGrid.querySelectorAll('.time-button').forEach(el => el.classList.toggle('is-selected', el === button));
                        toDetails.disabled = false;
                    });
                }
                timeGrid.appendChild(button);
            });
        } catch (error) {
            showSlotState(`<span>${error.message}</span>`);
        }
    };

    const getRecaptchaToken = async () => {
        if (!recaptcha.enabled) return null;
        if (!window.grecaptcha || !recaptcha.siteKey) {
            throw new Error('De beveiligingscontrole kon niet worden geladen. Vernieuw de pagina en probeer opnieuw.');
        }

        if ((recaptcha.mode || 'v2') === 'v2') {
            const response = window.grecaptcha.getResponse();
            if (!response) {
                throw new Error('Vink eerst “Ik ben geen robot” aan.');
            }
            return response;
        }

        return await new Promise((resolve, reject) => {
            window.grecaptcha.ready(() => {
                window.grecaptcha.execute(recaptcha.siteKey, { action: recaptcha.action || 'reservation' })
                    .then(resolve)
                    .catch(() => reject(new Error('De beveiligingscontrole kon niet worden uitgevoerd. Probeer opnieuw.')));
            });
        });
    };

    const resetRecaptcha = () => {
        if (recaptcha.enabled && (recaptcha.mode || 'v2') === 'v2' && window.grecaptcha) {
            try { window.grecaptcha.reset(); } catch (_) {}
        }
    };

    const validateStepOne = () => {
        if (!dateInput.value || !partyInput.value) return false;
        const date = new Date(`${dateInput.value}T12:00:00`);
        return !Number.isNaN(date.getTime());
    };

    document.querySelectorAll('[data-next]').forEach(button => {
        button.addEventListener('click', async () => {
            const target = Number(button.dataset.next);
            if (currentStep === 1) {
                if (!validateStepOne()) return;
                setStep(2);
                await fetchSlots();
                return;
            }
            if (currentStep === 2 && target === 3) {
                if (!timeInput.value) return;
                finalSummary.textContent = `${formatDate(dateInput.value)} om ${timeInput.value} · ${partyInput.value} ${Number(partyInput.value) === 1 ? 'persoon' : 'personen'}`;
                setStep(3);
            }
        });
    });

    document.querySelectorAll('[data-back]').forEach(button => {
        button.addEventListener('click', () => setStep(Number(button.dataset.back)));
    });

    dateInput.addEventListener('change', syncDateSelection);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        formError.hidden = true;

        const consent = document.getElementById('privacyConsent');
        if (!form.reportValidity() || !consent.checked) return;

        submitButton.disabled = true;
        const originalText = submitButton.textContent;
        submitButton.textContent = recaptcha.enabled ? 'Beveiliging controleren…' : 'Reservatie versturen…';

        const data = new FormData(form);
        const payload = Object.fromEntries(data.entries());
        payload.privacy_consent = consent.checked;
        payload.party_size = Number(payload.party_size);

        try {
            if (recaptcha.enabled) {
                payload.recaptcha_token = await getRecaptchaToken();
                submitButton.textContent = 'Reservatie versturen…';
            }

            const response = await fetch(`${api}?action=reserve`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.message || 'Reservatie kon niet worden opgeslagen.');

            form.hidden = true;
            document.querySelector('.booking-progress').hidden = true;
            success.hidden = false;
            document.getElementById('successCode').textContent = result.reservation.public_code || '—';
            document.getElementById('successSummary').textContent = `${formatDate(payload.date)} · ${payload.time} · ${payload.party_size} ${payload.party_size === 1 ? 'persoon' : 'personen'}`;
        } catch (error) {
            resetRecaptcha();
            formError.textContent = error.message;
            formError.hidden = false;
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = originalText;
        }
    });

    document.getElementById('newBooking').addEventListener('click', () => {
        window.location.reload();
    });

    renderDates();
    renderParty();
})();
