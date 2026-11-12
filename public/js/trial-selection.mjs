const form = document.querySelector('[data-trial-form]');
const session = form?.querySelector('#trial_session');

if (session) {
    const actions = form.querySelectorAll('button[data-intent]');
    const sync = () => {
        const intent = session.selectedOptions[0]?.dataset.intent;
        for (const action of actions) {
            action.hidden = Boolean(intent) && action.dataset.intent !== intent;
            action.disabled = !intent || action.hidden;
        }
    };
    session.addEventListener('change', sync);
    window.addEventListener('pageshow', sync);
    sync();
}

const countdown = document.querySelector('[data-trial-deadline]');
if (countdown) {
    const deadline = Date.parse(countdown.dataset.trialDeadline);
    const days = countdown.querySelector('[data-days]');
    const hours = countdown.querySelector('[data-hours]');
    const minutes = countdown.querySelector('[data-minutes]');

    const updateCountdown = () => {
        const remaining = Math.ceil((deadline - Date.now()) / 60000);
        countdown.hidden = !(remaining > 0);
        if (countdown.hidden) return;

        days.textContent = Math.floor(remaining / 1440);
        hours.textContent = String(Math.floor(remaining / 60) % 24).padStart(2, '0');
        minutes.textContent = String(remaining % 60).padStart(2, '0');
        window.setTimeout(updateCountdown, 1000);
    };

    updateCountdown();
}
