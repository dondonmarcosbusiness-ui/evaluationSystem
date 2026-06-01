const ADMIN_THEME_KEY = "theme_admin";
const LEGACY_THEME_KEY = "theme";

export function isAdminUser(user) {
  return user?.role === "admin";
}

function readAdminPreference() {
  const saved = localStorage.getItem(ADMIN_THEME_KEY);
  if (saved === "dark" || saved === "light") {
    return saved;
  }

  const legacy = localStorage.getItem(LEGACY_THEME_KEY);
  if (legacy === "dark" || legacy === "light") {
    localStorage.setItem(ADMIN_THEME_KEY, legacy);
    localStorage.removeItem(LEGACY_THEME_KEY);
    return legacy;
  }

  return "light";
}

export function getAdminThemeIsDark() {
  return readAdminPreference() === "dark";
}

/**
 * Apply theme for the current user. Non-admins always get light mode.
 * @returns {'dark'|'light'}
 */
export function syncThemeForUser(user) {
  const root = document.documentElement;

  if (!isAdminUser(user)) {
    root.removeAttribute("data-theme");
    return "light";
  }

  if (getAdminThemeIsDark()) {
    root.setAttribute("data-theme", "dark");
    return "dark";
  }

  root.removeAttribute("data-theme");
  return "light";
}

export function setAdminTheme(isDark) {
  localStorage.setItem(ADMIN_THEME_KEY, isDark ? "dark" : "light");
  localStorage.removeItem(LEGACY_THEME_KEY);

  if (isDark) {
    document.documentElement.setAttribute("data-theme", "dark");
  } else {
    document.documentElement.removeAttribute("data-theme");
  }
}
