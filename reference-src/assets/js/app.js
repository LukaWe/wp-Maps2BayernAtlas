/**
 * Maps to BayernAtlas Converter - Main JavaScript
 */

// Global state
let translations = {};
let currentLang = localStorage.getItem('lang') || 'de';

// DOM Elements (initialized after DOMContentLoaded)
let convertBtn, input, resultArea, resCoords, resUtm, resLink, resUrl, toast;
let langBtns, tabBtns, tabContents;

/**
 * Initialize the application
 */
function init() {
    // Cache DOM elements
    convertBtn = document.getElementById('convertBtn');
    input = document.getElementById('gmapsUrl');
    resultArea = document.getElementById('resultArea');
    resCoords = document.getElementById('resCoords');
    resUtm = document.getElementById('resUtm');
    resLink = document.getElementById('resLink');
    resUrl = document.getElementById('resUrl');
    toast = document.getElementById('toast');
    langBtns = document.querySelectorAll('.lang-btn');
    tabBtns = document.querySelectorAll('.tab-btn');
    tabContents = document.querySelectorAll('.tab-content');

    // Event listeners
    convertBtn.addEventListener('click', handleConvert);
    input.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') handleConvert();
    });

    // Language switcher
    langBtns.forEach(btn => {
        btn.addEventListener('click', () => setLanguage(btn.dataset.lang));
    });

    // Tab navigation
    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const tabName = btn.dataset.tab;
            tabBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            tabContents.forEach(content => {
                content.classList.toggle('active', content.id === `tab-${tabName}`);
            });
        });
    });

    // Initialize language
    setLanguage(currentLang);
}

/**
 * Load translations from JSON files
 */
async function loadTranslations() {
    try {
        const [deRes, enRes] = await Promise.all([
            fetch('lang/de.json'),
            fetch('lang/en.json')
        ]);
        translations = {
            de: await deRes.json(),
            en: await enRes.json()
        };
    } catch (error) {
        console.error('Failed to load translations:', error);
    }
}

/**
 * Set the current language
 */
function setLanguage(lang) {
    currentLang = lang;
    localStorage.setItem('lang', lang);

    // Update active button
    langBtns.forEach(btn => {
        btn.classList.toggle('active', btn.dataset.lang === lang);
    });

    // Update page title and lang attribute
    if (translations[lang]) {
        document.title = translations[lang].page_title;
        document.documentElement.lang = lang;

        // Update all translatable elements
        document.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.dataset.i18n;
            if (translations[lang][key]) {
                el.textContent = translations[lang][key];
            }
        });

        // Update placeholders
        document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
            const key = el.dataset.i18nPlaceholder;
            if (translations[lang][key]) {
                el.placeholder = translations[lang][key];
            }
        });

        // Update titles
        document.querySelectorAll('[data-i18n-title]').forEach(el => {
            const key = el.dataset.i18nTitle;
            if (translations[lang][key]) {
                el.title = translations[lang][key];
            }
        });
    }
}

/**
 * Get translation by key
 */
function t(key) {
    return translations[currentLang]?.[key] || key;
}

/**
 * Show toast notification
 */
function showToast(message, type = 'error') {
    toast.textContent = message;
    toast.className = 'toast show ' + type;
    setTimeout(() => {
        toast.className = toast.className.replace('show', '');
    }, 3000);
}

/**
 * Copy link to clipboard
 */
function copyLink() {
    const url = resLink.href;
    if (url && url !== '#') {
        navigator.clipboard.writeText(url).then(() => {
            showToast(t('toast_copy_success'), 'success');
        }).catch(() => {
            showToast(t('toast_copy_failed'), 'error');
        });
    }
}

/**
 * Handle conversion
 */
async function handleConvert() {
    const url = input.value.trim();
    if (!url) {
        showToast(t('toast_empty_url'));
        return;
    }

    // UI Loading State
    convertBtn.classList.add('loading');
    convertBtn.disabled = true;
    resultArea.classList.remove('active');

    try {
        const response = await fetch('/api/convert', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ gmaps_url: url })
        });

        const data = await response.json();

        if (!response.ok) {
            if (response.status === 429) {
                throw new Error(t('toast_rate_limit'));
            }
            if (response.status === 422) {
                throw new Error(t('toast_outside_bavaria'));
            }
            throw new Error(data.message || t('toast_conversion_failed'));
        }

        // Success
        resCoords.textContent = `${data.coordinates.lat.toFixed(6)}, ${data.coordinates.lon.toFixed(6)}`;
        resUtm.textContent = `${data.coordinates.easting} / ${data.coordinates.northing}`;
        resLink.href = data.bayernatlas_url;
        resUrl.textContent = data.bayernatlas_url;

        resultArea.classList.add('active');

    } catch (err) {
        showToast(err.message, 'error');
    } finally {
        convertBtn.classList.remove('loading');
        convertBtn.disabled = false;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', async () => {
    await loadTranslations();
    init();
});

// Expose copyLink globally for onclick handler
window.copyLink = copyLink;
