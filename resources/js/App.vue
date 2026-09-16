<template>
  <router-view></router-view>
  <SessionTimeoutManager />
  <ConfirmDialog />
  <SnackbarQueue />
</template>

<script setup>
import SessionTimeoutManager from "./components/SessionTimeoutManager.vue";
import ConfirmDialog from "./components/ConfirmDialog.vue";
import SnackbarQueue from "./components/SnackbarQueue.vue";
</script>
