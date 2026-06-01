import { readonly } from "vue";

export default {
  install: (app) => {
    const can = (permission) => {
      const userData = localStorage.getItem("user");
      if (!userData) return false;

      const user = JSON.parse(userData);

      const permissions = JSON.parse(localStorage.getItem("permissions") || "[]");

      if (Array.isArray(permission)) {
        return permission.some((p) => permissions.includes(p));
      }

      return permissions.includes(permission);
    };

    app.config.globalProperties.$can = can;
    app.provide("can", can);
  },
};
