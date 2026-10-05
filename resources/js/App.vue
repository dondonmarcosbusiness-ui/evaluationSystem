<template>
  <router-view></router-view>
  <SessionTimeoutManager />
  <ConfirmDialog />
  <SnackbarQueue />
</template>

<script setup>
import { onMounted, onUnmounted } from "vue";
import SessionTimeoutManager from "./components/SessionTimeoutManager.vue";
import ConfirmDialog from "./components/ConfirmDialog.vue";
import SnackbarQueue from "./components/SnackbarQueue.vue";
import { notifyError } from "./composables/useSnackbar.js";

function onForbidden(event) {
  notifyError(event.detail?.message || "You do not have permission to perform this action.");
}

onMounted(() => window.addEventListener("app:forbidden", onForbidden));
onUnmounted(() => window.removeEventListener("app:forbidden", onForbidden));
</script>
