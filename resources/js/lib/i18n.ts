import { useCallback, useEffect, useState } from 'react';

export type Locale = 'en' | 'hi';

const STORAGE_KEY = 'locale';

const strings = {
    en: {
        'nav.dashboard': 'Dashboard',
        'nav.customers': 'Customers',
        'nav.invoices': 'Invoices',
        'nav.payments': 'Payments',
        'nav.reports': 'Reports',
        'nav.reminders': 'Reminders',
        'nav.settings': 'Settings',
        'nav.billing': 'Billing',
        'nav.manage': 'Manage',
        'nav.platform': 'Platform',
        'nav.tenants': 'Tenants',
        'nav.allUsers': 'All users',
        'nav.plans': 'Plans',
        'nav.profile': 'Profile',
        'nav.business': 'Business',
        'nav.security': 'Security',
        'nav.appearance': 'Appearance',
        'nav.users': 'Users',
        'nav.activityLog': 'Activity Log',
        'nav.metalRates': 'Metal Rates',
        'nav.catalog': 'Catalog',
        'nav.chargeTypes': 'Charge Types',
        'nav.invoiceTemplates': 'Invoice Templates',
        'nav.logout': 'Log out',
        'auth.login': 'Log in',
        'auth.logout': 'Log out',
        'auth.register': 'Create account',
        'auth.forgot': 'Forgot password?',
        'auth.backToLogin': 'Back to log in',
        'auth.newHere': 'New here?',
        'auth.startTrial': 'Get started free',
        'auth.alreadyHave': 'Already have an account?',
        'auth.welcomeBack': 'Welcome back',
        'auth.loginBlurb': 'Log in to manage invoices and customers.',
'auth.createAccount': 'Create your free account',
        'auth.createBlurb': 'Set up your business in a couple of minutes. Every feature is included.',
        'auth.yourName': 'Your name',
        'auth.businessName': 'Business name',
        'auth.email': 'Email',
        'auth.workEmail': 'Work email',
        'auth.password': 'Password',
        'auth.newPassword': 'New password',
        'auth.confirmPassword': 'Confirm password',
        'auth.currentPassword': 'Current password',
        'auth.rememberMe': 'Remember me',
        'auth.emailLink': 'Email password reset link',
        'auth.resetPassword': 'Reset password',
        'auth.setNewPassword': 'Set a new password',
        'auth.setNewPasswordBlurb': 'Choose a strong password for your account.',
        'auth.forgotTitle': 'Forgot password',
        'auth.forgotBlurb': "Enter your account email and we'll send you a reset link.",
        'language.title': 'Language',
        'language.description': 'Choose the display language for menus and headings.',
        'language.english': 'English',
        'language.hindi': 'Hindi (à¤¹à¤¿à¤¨à¥à¤¦à¥€)',
    },
    hi: {
        'nav.dashboard': 'à¤¡à¥ˆà¤¶à¤¬à¥‹à¤°à¥à¤¡',
        'nav.customers': 'à¤—à¥à¤°à¤¾à¤¹à¤•',
        'nav.invoices': 'à¤šà¤¾à¤²à¤¾à¤¨',
        'nav.payments': 'à¤­à¥à¤—à¤¤à¤¾à¤¨',
        'nav.reports': 'à¤°à¤¿à¤ªà¥‹à¤°à¥à¤Ÿ',
        'nav.reminders': 'à¤°à¤¿à¤®à¤¾à¤‡à¤‚à¤¡à¤°',
        'nav.settings': 'à¤¸à¥‡à¤Ÿà¤¿à¤‚à¤—à¥à¤¸',
        'nav.billing': 'à¤¬à¤¿à¤²à¤¿à¤‚à¤—',
        'nav.manage': 'à¤ªà¥à¤°à¤¬à¤‚à¤§à¤¨',
        'nav.platform': 'प्लेटफ़ोर्म',
        'nav.tenants': 'बिज़नेस',
        'nav.allUsers': 'सभी उपयोगकर्ता',
        'nav.plans': 'योजनाएँ',
        'nav.profile': 'à¤ªà¥à¤°à¥‹à¤«à¤¼à¤¾à¤‡à¤²',
        'nav.business': 'à¤µà¥à¤¯à¤µà¤¸à¤¾à¤¯',
        'nav.security': 'à¤¸à¥à¤°à¤•à¥à¤·à¤¾',
        'nav.appearance': 'à¤¦à¤¿à¤–à¤¾à¤µà¤Ÿ',
        'nav.users': 'à¤‰à¤ªà¤¯à¥‹à¤—à¤•à¤°à¥à¤¤à¤¾',
        'nav.activityLog': 'à¤—à¤¤à¤¿à¤µà¤¿à¤§à¤¿ à¤²à¥‰à¤—',
        'nav.metalRates': 'à¤§à¤¾à¤¤à¥ à¤¦à¤°à¥‡à¤‚',
        'nav.catalog': 'à¤•à¥ˆà¤Ÿà¤²à¥‰à¤—',
        'nav.chargeTypes': 'à¤¶à¥à¤²à¥à¤• à¤ªà¥à¤°à¤•à¤¾à¤°',
        'nav.invoiceTemplates': 'à¤šà¤¾à¤²à¤¾à¤¨ à¤Ÿà¥‡à¤®à¥à¤ªà¤²à¥‡à¤Ÿ',
        'nav.logout': 'à¤²à¥‰à¤— à¤†à¤‰à¤Ÿ',
        'auth.login': 'à¤²à¥‰à¤— à¤‡à¤¨',
        'auth.logout': 'à¤²à¥‰à¤— à¤†à¤‰à¤Ÿ',
        'auth.register': 'à¤–à¤¾à¤¤à¤¾ à¤¬à¤¨à¤¾à¤à¤‚',
        'auth.forgot': 'à¤ªà¤¾à¤¸à¤µà¤°à¥à¤¡ à¤­à¥‚à¤² à¤—à¤?',
        'auth.backToLogin': 'à¤²à¥‰à¤— à¤‡à¤¨ à¤ªà¤° à¤µà¤¾à¤ªà¤¸ à¤œà¤¾à¤à¤‚',
        'auth.newHere': 'à¤¨à¤ à¤¹à¥ˆà¤‚?',
        'auth.startTrial': 'Get started free',
        'auth.alreadyHave': 'à¤ªà¤¹à¤²à¥‡ à¤¸à¥‡ à¤–à¤¾à¤¤à¤¾ à¤¹à¥ˆ?',
        'auth.welcomeBack': 'à¤µà¤¾à¤ªà¤¸à¥€ à¤ªà¤° à¤¸à¥à¤µà¤¾à¤—à¤¤ à¤¹à¥ˆ',
        'auth.loginBlurb': 'à¤šà¤¾à¤²à¤¾à¤¨ à¤”à¤° à¤—à¥à¤°à¤¾à¤¹à¤•à¥‹à¤‚ à¤•à¤¾ à¤ªà¥à¤°à¤¬à¤‚à¤§à¤¨ à¤•à¤°à¤¨à¥‡ à¤•à¥‡ à¤²à¤¿à¤ à¤²à¥‰à¤— à¤‡à¤¨ à¤•à¤°à¥‡à¤‚à¥¤',
'auth.createAccount': 'Create your free account',
        'auth.createBlurb': 'Set up your business in a couple of minutes. Every feature is included.',
        'auth.yourName': 'à¤†à¤ªà¤•à¤¾ à¤¨à¤¾à¤®',
        'auth.businessName': 'à¤µà¥à¤¯à¤µà¤¸à¤¾à¤¯ à¤•à¤¾ à¤¨à¤¾à¤®',
        'auth.email': 'à¤ˆà¤®à¥‡à¤²',
        'auth.workEmail': 'à¤•à¤¾à¤°à¥à¤¯ à¤ˆà¤®à¥‡à¤²',
        'auth.password': 'à¤ªà¤¾à¤¸à¤µà¤°à¥à¤¡',
        'auth.newPassword': 'à¤¨à¤¯à¤¾ à¤ªà¤¾à¤¸à¤µà¤°à¥à¤¡',
        'auth.confirmPassword': 'à¤ªà¤¾à¤¸à¤µà¤°à¥à¤¡ à¤•à¥€ à¤ªà¥à¤·à¥à¤Ÿà¤¿ à¤•à¤°à¥‡à¤‚',
        'auth.currentPassword': 'à¤µà¤°à¥à¤¤à¤®à¤¾à¤¨ à¤ªà¤¾à¤¸à¤µà¤°à¥à¤¡',
        'auth.rememberMe': 'à¤®à¥à¤à¥‡ à¤¯à¤¾à¤¦ à¤°à¤–à¥‡à¤‚',
        'auth.emailLink': 'à¤ªà¤¾à¤¸à¤µà¤°à¥à¤¡ à¤°à¥€à¤¸à¥‡à¤Ÿ à¤²à¤¿à¤‚à¤• à¤­à¥‡à¤œà¥‡à¤‚',
        'auth.resetPassword': 'à¤ªà¤¾à¤¸à¤µà¤°à¥à¤¡ à¤°à¥€à¤¸à¥‡à¤Ÿ à¤•à¤°à¥‡à¤‚',
        'auth.setNewPassword': 'à¤¨à¤¯à¤¾ à¤ªà¤¾à¤¸à¤µà¤°à¥à¤¡ à¤¸à¥‡à¤Ÿ à¤•à¤°à¥‡à¤‚',
        'auth.setNewPasswordBlurb': 'à¤…à¤ªà¤¨à¥‡ à¤–à¤¾à¤¤à¥‡ à¤•à¥‡ à¤²à¤¿à¤ à¤®à¤œà¤¬à¥‚à¤¤ à¤ªà¤¾à¤¸à¤µà¤°à¥à¤¡ à¤šà¥à¤¨à¥‡à¤‚à¥¤',
        'auth.forgotTitle': 'à¤ªà¤¾à¤¸à¤µà¤°à¥à¤¡ à¤­à¥‚à¤² à¤—à¤',
        'auth.forgotBlurb': 'à¤…à¤ªà¤¨à¥‡ à¤–à¤¾à¤¤à¥‡ à¤•à¤¾ à¤ˆà¤®à¥‡à¤² à¤¦à¤°à¥à¤œ à¤•à¤°à¥‡à¤‚, à¤¹à¤® à¤°à¥€à¤¸à¥‡à¤Ÿ à¤²à¤¿à¤‚à¤• à¤­à¥‡à¤œà¥‡à¤‚à¤—à¥‡à¥¤',
        'language.title': 'à¤­à¤¾à¤·à¤¾',
        'language.description': 'à¤®à¥‡à¤¨à¥‚ à¤”à¤° à¤¶à¥€à¤°à¥à¤·à¤•à¥‹à¤‚ à¤•à¥‡ à¤²à¤¿à¤ à¤ªà¥à¤°à¤¦à¤°à¥à¤¶à¤¨ à¤­à¤¾à¤·à¤¾ à¤šà¥à¤¨à¥‡à¤‚à¥¤',
        'language.english': 'English',
        'language.hindi': 'Hindi (à¤¹à¤¿à¤¨à¥à¤¦à¥€)',
    },
} as const;

export type I18nKey = keyof (typeof strings)['en'];

export function getLocale(): Locale {
    if (typeof window === 'undefined') return 'en';
    return window.localStorage.getItem(STORAGE_KEY) === 'hi' ? 'hi' : 'en';
}

export function setLocale(locale: Locale): void {
    window.localStorage.setItem(STORAGE_KEY, locale);
    document.documentElement.lang = locale === 'hi' ? 'hi' : 'en';
}

export function translate(locale: Locale, key: I18nKey): string {
    return strings[locale][key] ?? strings.en[key] ?? key;
}

export function useLocale() {
    const [locale, setLocaleState] = useState<Locale>(() => getLocale());

    useEffect(() => {
        document.documentElement.lang = locale === 'hi' ? 'hi' : 'en';
    }, [locale]);

    const updateLocale = useCallback((next: Locale) => {
        setLocale(next);
        setLocaleState(next);
    }, []);

    const t = useCallback((key: I18nKey) => translate(locale, key), [locale]);

    return { locale, updateLocale, t };
}

export function initializeLocale(): void {
    if (typeof window === 'undefined') return;
    document.documentElement.lang = getLocale() === 'hi' ? 'hi' : 'en';
}
