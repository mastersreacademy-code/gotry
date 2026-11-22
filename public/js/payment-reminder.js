const reminder = document.querySelector('[data-payment-reminder]');
if (reminder && typeof reminder.showModal === 'function') {
    reminder.showModal();
    reminder.addEventListener('click', event => {
        if (event.target.closest('a')) reminder.close();
    });
    window.addEventListener('pageshow', event => {
        if (event.persisted && reminder.open) reminder.close();
    });
}
