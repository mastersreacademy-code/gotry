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
