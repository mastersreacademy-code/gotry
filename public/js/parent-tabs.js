const navigation = document.querySelector('.portal-navigation');
if (navigation) {
    const tabs = [...navigation.querySelectorAll('a[href^="#"]')];
    const panels = [...document.querySelectorAll('.portal-group')];
    if (tabs.length && panels.length) {
        navigation.setAttribute('role', 'tablist');
        tabs.forEach(tab => {
            tab.id = 'tab-' + tab.hash.slice(1);
            tab.setAttribute('role', 'tab');
            tab.setAttribute('aria-controls', tab.hash === '#all-registrations'
                ? panels.map(panel => panel.id).join(' ') : tab.hash.slice(1));
        });
        panels.forEach(panel => {
            panel.setAttribute('role', 'tabpanel');
            panel.tabIndex = 0;
        });

        function activate(index) {
            const selected = tabs[index];
            const all = selected.hash === '#all-registrations';
            const hasEntries = panels.some(panel => Number(panel.dataset.entryCount) > 0);
            tabs.forEach((tab, position) => {
                tab.setAttribute('aria-selected', String(position === index));
                tab.tabIndex = position === index ? 0 : -1;
            });
            panels.forEach((panel, position) => {
                panel.hidden = all
                    ? (hasEntries ? Number(panel.dataset.entryCount) === 0 : position !== 0)
                    : '#' + panel.id !== selected.hash;
                panel.setAttribute('aria-labelledby', selected.id);
            });
        }
        function selectFromHash() {
            const index = tabs.findIndex(tab => tab.hash === location.hash);
            activate(index < 0 ? 0 : index);
        }
        tabs.forEach((tab, index) => {
            tab.addEventListener('click', event => {
                event.preventDefault();
                history.replaceState(null, '', tab.hash);
                activate(index);
            });
            tab.addEventListener('keydown', event => {
                let next;
                if (['ArrowRight', 'ArrowDown'].includes(event.key)) next = (index + 1) % tabs.length;
                else if (['ArrowLeft', 'ArrowUp'].includes(event.key)) next = (index + tabs.length - 1) % tabs.length;
                else if (event.key === 'Home') next = 0;
                else if (event.key === 'End') next = tabs.length - 1;
                else return;
                event.preventDefault();
                tabs[next].click();
                tabs[next].focus();
            });
        });
        window.addEventListener('hashchange', selectFromHash);
        window.addEventListener('pageshow', selectFromHash);
        selectFromHash();
    }
}
