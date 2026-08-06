document.addEventListener('DOMContentLoaded', function () {
    const tabs = document.querySelectorAll('.eventon-apify-tab');
    const panels = document.querySelectorAll('.eventon-apify-panel');

    /*
     * Saving posts to options.php, which redirects to
     * add_query_arg('settings-updated', 'true', wp_get_referer()). wp_get_referer()
     * reads the _wp_http_referer field, and add_query_arg preserves a fragment, so
     * keeping the tab in that field is what carries the active tab across the save.
     * Without it every save landed the user back on the first tab.
     */
    const refererField = document.querySelector('.eventon-apify-shell input[name="_wp_http_referer"]');

    function rememberTabForSave(targetPanel) {
        if (!refererField) {
            return;
        }

        refererField.value = refererField.value.split('#')[0] + '#' + targetPanel;
    }

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

        if (!hasMatch) {
            return;
        }

        rememberTabForSave(targetPanel);

        if (updateHash) {
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

    // --- copy-to-clipboard buttons ----------------------------------------

    // Localized by wp_localize_script(); the fallbacks only matter if the
    // handle failed to load its inline data.
    const strings = window.eventonApifySettings || {};
    const copiedLabel = strings.copiedLabel || 'Copied';
    const copyFailedLabel = strings.copyFailedLabel || 'Press Ctrl+C';

    function flashButtonLabel(button, message) {
        if (button.dataset.originalLabel === undefined) {
            button.dataset.originalLabel = button.textContent;
        }

        button.textContent = message;
        window.clearTimeout(Number(button.dataset.resetTimer));
        button.dataset.resetTimer = String(window.setTimeout(function () {
            button.textContent = button.dataset.originalLabel;
        }, 2000));
    }

    /**
     * Copy text without the async Clipboard API, which is undefined on insecure
     * origins. Keeps the buttons working on plain-HTTP staging installs.
     */
    function copyWithFallback(text) {
        const scratch = document.createElement('textarea');
        scratch.value = text;
        scratch.setAttribute('readonly', '');
        scratch.style.position = 'fixed';
        scratch.style.opacity = '0';
        document.body.appendChild(scratch);
        scratch.select();

        let copied = false;
        try {
            copied = document.execCommand('copy');
        } catch (error) {
            copied = false;
        }

        document.body.removeChild(scratch);

        return copied;
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-eventon-apify-copy]');
        if (!button) {
            return;
        }

        event.preventDefault();

        const source = document.getElementById(button.getAttribute('data-eventon-apify-copy'));
        if (!source) {
            return;
        }

        const text = source.textContent;

        function reportFallbackResult() {
            flashButtonLabel(button, copyWithFallback(text) ? copiedLabel : copyFailedLabel);
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function () {
                flashButtonLabel(button, copiedLabel);
            }, reportFallbackResult);
            return;
        }

        reportFallbackResult();
    });
});
