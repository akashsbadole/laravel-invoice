import { useCallback, useEffect, useState } from 'react';

export type Locale = 'en' | 'hi';

const STORAGE_KEY = 'locale';

const strings = {
    en: {
        'nav.dashboard': 'Dashboard',
        'nav.customers': 'Customers',
        'nav.invoices': 'Invoices',
        'nav.quotations': 'Quotations',
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
        'nav.customerGroups': 'Customer Groups',
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
        'nav.dashboard': 'डैशबोर्ड',
        'nav.customers': 'ग्राहक',
        'nav.invoices': 'इन्वोइस',
        'nav.quotations': 'कोटेशन',
        'nav.payments': 'मुनादान',
        'nav.reports': 'रिपोर्ट',
        'nav.reminders': 'स्मरण',
        'nav.settings': 'सेटिंगस',
        'nav.billing': 'बिलिंग',
        'nav.manage': 'व्यवस्थापरण',
        'nav.platform': 'प्लेटफॉर्म',
        'nav.tenants': 'बिज़नेस',
        'nav.allUsers': 'सभी उपयोगकर्ता',
        'nav.plans': 'योजनाएँ',
        'nav.profile': 'प्रोफाइल',
        'nav.business': 'बिज़नेस',
        'nav.security': 'सुरक्षा',
        'nav.appearance': 'आपियान',
        'nav.users': 'उपयोगकर्ता',
        'nav.activityLog': 'एक्टिविटी लोग',
        'nav.metalRates': 'मेटल दरे',
        'nav.catalog': 'कीटालोग',
        'nav.chargeTypes': 'चार्जी प्रकार',
        'nav.customerGroups': 'ग्राहक समूह',
        'nav.invoiceTemplates': 'इन्वोइस टेम्प्लेट',
        'nav.logout': 'लगओट आउट',
        'auth.login': 'लगिन',
        'auth.logout': 'लगओट आउट',
        'auth.register': 'रैगिस्टर',
        'auth.forgot': 'पासवर्ड भूले',
        'auth.backToLogin': 'लगिन पर वापस',
        'auth.newHere': 'नए हैं?',
        'auth.startTrial': 'मफत शुरू करें',
        'auth.alreadyHave': 'पहले से है?',
        'auth.welcomeBack': 'पुनओं स्वागत',
        'auth.loginBlurb': 'शीरोपरी प्रकारमान हमेना करनें',
        'auth.createAccount': 'मफत खाता क्रण बनाए',
        'auth.createBlurb': 'दो मिनट में अपना कार्य करां। सभी सुविधा शामिल',
        'auth.yourName': 'आपका नाम',
        'auth.businessName': 'बिज़नेस का नाम',
        'auth.email': 'ईमैल',
        'auth.workEmail': 'काम का ईमैल',
        'auth.password': 'पासवर्ड',
        'auth.newPassword': 'नया पासवर्ड',
        'auth.confirmPassword': 'पासवर्ड को सत्यापृट',
        'auth.currentPassword': 'वर्तमान पासवर्ड',
        'auth.rememberMe': 'मेना रहे',
        'auth.emailLink': 'ईमैल लिंक',
        'auth.resetPassword': 'पासवर्ड रिसेट',
        'auth.setNewPassword': 'नया पासवर्ड सेट',
        'auth.setNewPasswordBlurb': 'एक नया पासवर्ड चुनाए',
        'auth.forgotTitle': 'पासवर्ड भूलै',
        'auth.forgotBlurb': 'अपका का ईमैल दिन्ट',
        'language.title': 'भाषा',
        'language.description': 'अपनी टोत्ट',
        'language.english': 'अंग्रेजि',
        'language.hindi': 'हिन्दी',
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
