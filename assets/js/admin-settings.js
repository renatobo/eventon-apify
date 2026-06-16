function eventonApifyCopy(elementId) {
    const source = document.getElementById(elementId);

    if (!source) {
        return;
    }

    navigator.clipboard.writeText(source.textContent);
}

document.addEventListener('DOMContentLoaded', function () {
    const tabs = document.querySelectorAll('.eventon-apify-tab');
    const panels = document.querySelectorAll('.eventon-apify-panel');

    function activateTab(targetPanel, updateHash) {
        let hasMatch = false;

        tabs.forEach(function (item) {
            const isTarget = item.getAttribute('data-panel') === targetPanel;
            item.classList.toggle('nav-tab-active', isTarget);
            item.setAttribute('aria-selected', isTarget ? 'true' : 'false');
            hasMatch = hasMatch || isTarget;
        });

        panels.forEach(function (panel) {
            const isTarget = panel.getAttribute('data-panel') === targetPanel;
            panel.classList.toggle('is-active', isTarget);
            panel.hidden = !isTarget;
        });

        if (hasMatch && updateHash) {
            window.location.hash = targetPanel;
        }
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function (event) {
            event.preventDefault();
            activateTab(tab.getAttribute('data-panel'), true);
        });
    });

    const initialPanel = window.location.hash ? window.location.hash.replace('#', '') : 'api';
    activateTab(initialPanel, false);

    window.addEventListener('hashchange', function () {
        const hashPanel = window.location.hash ? window.location.hash.replace('#', '') : 'api';
        activateTab(hashPanel, false);
    });
});
