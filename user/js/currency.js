// currency.js — Global currency utility for all pages

const currencySymbols = {
    'EUR': '<i class="fa-solid fa-euro-sign"></i>',
    'USD': '<i class="fa-solid fa-dollar-sign"></i>',
    'GBP': '<i class="fa-solid fa-sterling-sign"></i>',
    'JPY': '<i class="fa-solid fa-yen-sign"></i>',
    'CHF': '<i class="fa-solid fa-franc-sign"></i>',
    'INR': '<i class="fa-solid fa-indian-rupee-sign"></i>',
    'RUB': '<i class="fa-solid fa-ruble-sign"></i>',
    'TRY': '<i class="fa-solid fa-turkish-lira-sign"></i>',
    'KRW': '<i class="fa-solid fa-won-sign"></i>'
};

const currencyTextSymbols = {
    'EUR': '€',
    'USD': '$',
    'GBP': '£',
    'JPY': '¥',
    'CHF': 'CHF',
    'INR': '₹',
    'RUB': '₽',
    'TRY': '₺',
    'KRW': '₩'
};

const currencyNames = {
    'EUR': 'Eiro',
    'USD': 'Dolārs',
    'GBP': 'Sterliņu mārciņa',
    'JPY': 'Japānas Jena',
    'CHF': 'Šveices Franks',
    'INR': 'Indijas Rupija',
    'RUB': 'Krievijas Rublis',
    'TRY': 'Turcijas Lira',
    'KRW': 'Korejas Vona'
};

// Track currency change listeners
const currencyListeners = [];

/**
 * Register a callback to be called whenever the currency changes
 * @param {Function} callback - Function to call with (newCurrency, symbol)
 */
function onCurrencyChange(callback) {
    currencyListeners.push(callback);
}

/**
 * Get the current currency preference from localStorage
 * Falls back to EUR if not set
 */
function getCurrentCurrency() {
    return localStorage.getItem('budgetiva_currency') || 'EUR';
}

/**
 * Get the currency symbol for a given currency code
 * @param {string} code - Currency code (e.g., 'EUR', 'USD')
 * @returns {string} - Currency symbol
 */
function getCurrencySymbol(code) {
    return currencySymbols[code] || code;
}

function getCurrencyTextSymbol(code) {
    return currencyTextSymbols[code] || code;
}

/**
 * Format an amount with the current currency
 * @param {number} amount - The amount to format
 * @param {string} code - Optional currency code; uses current if not provided
 * @returns {string} - Formatted amount (e.g., "€12.50")
 */
function formatCurrency(amount, code = null) {
    const currency = code || getCurrentCurrency();
    const symbol = getCurrencyTextSymbol(currency);
    const formatted = parseFloat(amount).toFixed(2);
    return `${symbol}${formatted}`;
}

/**
 * Get formatted currency for display in a specific way
 * @param {number} amount - The amount to format
 * @param {string} prefix - Optional prefix (default: symbol first)
 * @returns {string} - Formatted amount
 */
function formatCurrencyAlt(amount, prefix = true) {
    const currency = getCurrentCurrency();
    const symbol = getCurrencyTextSymbol(currency);
    const formatted = parseFloat(amount).toFixed(2);
    return prefix ? `${symbol}${formatted}` : `${formatted} ${symbol}`;
}

/**
 * Notify all listeners of a currency change
 * @param {string} newCurrency - The new currency code
 */
function notifyCurrencyChange(newCurrency) {
    const symbol = getCurrencySymbol(newCurrency);
    currencyListeners.forEach(callback => {
        try {
            callback(newCurrency, symbol);
        } catch (e) {
            console.error('Error in currency change listener:', e);
        }
    });
}
