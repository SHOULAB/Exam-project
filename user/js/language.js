(function () {
    'use strict';

    var LS_KEY = 'budgetar_language';

    function applyTranslations(T, lang) {
        var dict = T[lang] || T['lv'];
        document.querySelectorAll('[data-i18n]').forEach(function (el) {
            var key = el.getAttribute('data-i18n');
            if (dict[key] !== undefined) el.textContent = dict[key];
        });
        document.querySelectorAll('.lang-btn').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.lang === lang);
        });
    }

    // Prefer localStorage so the latest user selection always wins,
    // fall back to the PHP-session value inlined by the page.
    var lang = localStorage.getItem(LS_KEY) || (window._i18nLang || 'lv');

    if (window._i18nData) {
        // Translation data was inlined by PHP — apply synchronously, no flash.
        applyTranslations(window._i18nData, lang);
        window._i18n = { T: window._i18nData, lang: lang, apply: applyTranslations };
    } else {
        // Fallback: fetch the JSON file (only when no inline data is present).
        fetch('../php/translate.json')
            .then(function (r) { return r.json(); })
            .then(function (T) {
                window._i18n = { T: T, lang: lang, apply: applyTranslations };
                applyTranslations(T, lang);
            });
    }
})();
