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
        'auth.startTrial': 'Start your free trial',
        'auth.alreadyHave': 'Already have an account?',
        'auth.welcomeBack': 'Welcome back',
        'auth.loginBlurb': 'Log in to manage invoices and customers.',
        'auth.createAccount': 'Start your 14-day trial',
        'auth.createBlurb': "Your business, invoices and customers — free for 14 days, no card required.",
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
        'language.hindi': 'Hindi (हिन्दी)',
    },
    hi: {
        'nav.dashboard': 'डैशबोर्ड',
        'nav.customers': 'ग्राहक',
        'nav.invoices': 'चालान',
        'nav.payments': 'भुगतान',
        'nav.reports': 'रिपोर्ट',
        'nav.reminders': 'रिमाइंडर',
        'nav.settings': 'सेटिंग्स',
        'nav.billing': 'बिलिंग',
        'nav.manage': 'प्रबंधन',
        'nav.profile': 'प्रोफ़ाइल',
        'nav.business': 'व्यवसाय',
        'nav.security': 'सुरक्षा',
        'nav.appearance': 'दिखावट',
        'nav.users': 'उपयोगकर्ता',
        'nav.activityLog': 'गतिविधि लॉग',
        'nav.metalRates': 'धातु दरें',
        'nav.catalog': 'कैटलॉग',
        'nav.chargeTypes': 'शुल्क प्रकार',
        'nav.invoiceTemplates': 'चालान टेम्पलेट',
        'nav.logout': 'लॉग आउट',
        'auth.login': 'लॉग इन',
        'auth.logout': 'लॉग आउट',
        'auth.register': 'खाता बनाएं',
        'auth.forgot': 'पासवर्ड भूल गए?',
        'auth.backToLogin': 'लॉग इन पर वापस जाएं',
        'auth.newHere': 'नए हैं?',
        'auth.startTrial': 'मुफ्त ट्रायल शुरू करें',
        'auth.alreadyHave': 'पहले से खाता है?',
        'auth.welcomeBack': 'वापसी पर स्वागत है',
        'auth.loginBlurb': 'चालान और ग्राहकों का प्रबंधन करने के लिए लॉग इन करें।',
        'auth.createAccount': '14-दिन का ट्रायल शुरू करें',
        'auth.createBlurb': 'आपका व्यवसाय, चालान और ग्राहक — 14 दिन मुफ्त, कार्ड की आवश्यकता नहीं।',
        'auth.yourName': 'आपका नाम',
        'auth.businessName': 'व्यवसाय का नाम',
        'auth.email': 'ईमेल',
        'auth.workEmail': 'कार्य ईमेल',
        'auth.password': 'पासवर्ड',
        'auth.newPassword': 'नया पासवर्ड',
        'auth.confirmPassword': 'पासवर्ड की पुष्टि करें',
        'auth.currentPassword': 'वर्तमान पासवर्ड',
        'auth.rememberMe': 'मुझे याद रखें',
        'auth.emailLink': 'पासवर्ड रीसेट लिंक भेजें',
        'auth.resetPassword': 'पासवर्ड रीसेट करें',
        'auth.setNewPassword': 'नया पासवर्ड सेट करें',
        'auth.setNewPasswordBlurb': 'अपने खाते के लिए मजबूत पासवर्ड चुनें।',
        'auth.forgotTitle': 'पासवर्ड भूल गए',
        'auth.forgotBlurb': 'अपने खाते का ईमेल दर्ज करें, हम रीसेट लिंक भेजेंगे।',
        'language.title': 'भाषा',
        'language.description': 'मेनू और शीर्षकों के लिए प्रदर्शन भाषा चुनें।',
        'language.english': 'English',
        'language.hindi': 'Hindi (हिन्दी)',
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
