const navigation = document.querySelector('.portal-navigation');
if (navigation) {
    const tabs = [...navigation.querySelectorAll('a[href^="#"]')];
    const panels = tabs.map(tab => document.getElementById(tab.hash.slice(1)));
    if (panels.every(Boolean)) {
        navigation.setAttribute('role', 'tablist');
        tabs.forEach((tab, index) => {
            tab.id = 'tab-' + panels[index].id;
            tab.setAttribute('role', 'tab');
            tab.setAttribute('aria-controls', panels[index].id);
            panels[index].setAttribute('role', 'tabpanel');
            panels[index].setAttribute('aria-labelledby', tab.id);
            panels[index].tabIndex = 0;
        });
        function activate(index) {
            tabs.forEach((tab, position) => {
                const selected = position === index;
                tab.setAttribute('aria-selected', String(selected));
                tab.tabIndex = selected ? 0 : -1;
                panels[position].hidden = !selected;
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
                if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
                else if (event.key === 'ArrowLeft') next = (index + tabs.length - 1) % tabs.length;
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
