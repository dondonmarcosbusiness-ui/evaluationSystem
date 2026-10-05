import "./bootstrap";
import "bootstrap/dist/css/bootstrap.min.css";
import "../css/app.css";

import { createApp } from "vue";
import App from "./App.vue";
import router from "./router/index.js";
import permissions, { refreshPermissions } from "./helpers/permissions.js";
import api from "./services/api.js";

const app = createApp(App);
app.use(router);
app.use(permissions);

// Pull the freshest permission list from the server before the SPA settles,
// so admin permission changes apply on the next page load.
refreshPermissions(api);

app.mount("#app");
