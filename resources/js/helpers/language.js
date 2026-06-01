import { ref, reactive } from 'vue';

const currentLang = ref(localStorage.getItem('app_lang') || 'en');

export function useLanguage() {
    const setLanguage = (lang) => {
        currentLang.value = lang;
        localStorage.setItem('app_lang', lang);
    };

    return {
        currentLang,
        setLanguage
    };
}
