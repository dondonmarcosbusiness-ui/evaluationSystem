const getPermissions = () => {
  try {
    return JSON.parse(localStorage.getItem("permissions") || "[]");
  } catch {
    return [];
  }
};

const can = (permission) => {
  const userData = localStorage.getItem("user");
  if (!userData) return false;
  if (!permission) return true;

  const permissions = getPermissions();

  if (Array.isArray(permission)) {
    return permission.some((p) => permissions.includes(p));
  }

  return permissions.includes(permission);
};

// Resolve the data-scope the current user holds for a base permission.
// Mirrors AuthorizationService::scope(): .all > .team > .own, a bare base
// grant resolves to "own", and a missing base grant resolves to "none".
const scope = (permission) => {
  if (!can(permission)) return "none";
  const permissions = getPermissions();
  if (permissions.includes(`${permission}.all`)) return "all";
  if (permissions.includes(`${permission}.team`)) return "team";
  return "own";
};

// Re-fetch the signed-in user's permissions from the server so role/permission
// changes made by an admin take effect on the next page load (before any API
// call is made). Silently keeps the cached list when offline/unauthenticated.
const refreshPermissions = async (api) => {
  if (!localStorage.getItem("token")) return;
  try {
    const res = await api.get("/user");
    if (Array.isArray(res.data.permissions)) {
      localStorage.setItem("permissions", JSON.stringify(res.data.permissions));
    }
    if (res.data.user) {
      localStorage.setItem("user", JSON.stringify(res.data.user));
    }
  } catch {
    // Keep the cached permissions; the API layer still enforces server-side.
  }
};

export { can, scope, getPermissions, refreshPermissions };

export default {
  install: (app) => {
    app.config.globalProperties.$can = can;
    app.config.globalProperties.$scope = scope;
    app.provide("can", can);
    app.provide("scope", scope);
  },
};
