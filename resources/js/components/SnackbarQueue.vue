<template>
  <Teleport to="body">
    <div class="snackbar-queue" aria-live="polite" aria-atomic="false">
      <TransitionGroup name="snackbar">
        <div v-for="s in visible" :key="s.id" class="snackbar" :class="'snackbar--' + s.type" role="status">
          <div class="snackbar__icon" aria-hidden="true">
            <i :class="iconFor(s.type)"></i>
          </div>
          <div class="snackbar__body">
            <div v-if="s.title" class="snackbar__title">{{ s.title }}</div>
            <div class="snackbar__msg">{{ s.message }}</div>
          </div>
          <button class="snackbar__close" type="button" aria-label="Dismiss notification" @click="dismiss(s.id)">
            <i class="fas fa-times"></i>
          </button>
          <span class="snackbar__bar" :style="{ animationDuration: s.duration + 'ms' }" aria-hidden="true"></span>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup>
import { snackbarVisible as visible, dismissSnackbar as dismiss } from "../composables/useSnackbar.js";

const icons = {
  info: "fas fa-circle-info",
  success: "fas fa-circle-check",
  warning: "fas fa-triangle-exclamation",
  error: "fas fa-circle-xmark",
};

function iconFor(type) {
  return icons[type] || icons.info;
}
</script>

<style scoped>
.snackbar-queue {
  position: fixed;
  left: 50%;
  bottom: 1.5rem;
  transform: translateX(-50%);
  z-index: 3000;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.6rem;
  width: min(430px, calc(100vw - 2rem));
  pointer-events: none;
}

.snackbar {
  pointer-events: auto;
  position: relative;
  width: 100%;
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.85rem 2.25rem 0.95rem 0.9rem;
  background: var(--bg-card, #fff);
  border: 1px solid var(--border-color);
  border-radius: 14px;
  box-shadow: 0 12px 32px rgba(15, 23, 42, 0.18);
  overflow: hidden;
}

.snackbar__icon {
  width: 32px;
  height: 32px;
  flex-shrink: 0;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
}

.snackbar--info .snackbar__icon {
  color: #1f6feb;
  background: rgba(31, 111, 235, 0.12);
}

.snackbar--success .snackbar__icon {
  color: #16a34a;
  background: rgba(34, 197, 94, 0.12);
}

.snackbar--warning .snackbar__icon {
  color: #d97706;
  background: rgba(217, 119, 6, 0.12);
}

.snackbar--error .snackbar__icon {
  color: #dc2626;
  background: rgba(239, 68, 68, 0.12);
}

.snackbar__body {
  flex: 1;
  min-width: 0;
  padding-top: 1px;
}

.snackbar__title {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--text-dark);
  margin-bottom: 2px;
}

.snackbar__msg {
  font-size: 0.78rem;
  line-height: 1.45;
  color: var(--text-muted);
}

.snackbar__close {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 24px;
  height: 24px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: none;
  border-radius: 6px;
  background: transparent;
  color: var(--text-muted);
  font-size: 0.7rem;
  cursor: pointer;
  transition: all 0.15s ease;
}

.snackbar__close:hover {
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
}

.snackbar__bar {
  position: absolute;
  left: 0;
  bottom: 0;
  height: 3px;
  width: 100%;
  transform-origin: left;
  animation-name: snackbar-shrink;
  animation-timing-function: linear;
  animation-fill-mode: forwards;
  background: #1f6feb;
}

.snackbar--success .snackbar__bar {
  background: #16a34a;
}

.snackbar--warning .snackbar__bar {
  background: #d97706;
}

.snackbar--error .snackbar__bar {
  background: #dc2626;
}

@keyframes snackbar-shrink {
  from {
    transform: scaleX(1);
  }
  to {
    transform: scaleX(0);
  }
}

.snackbar-enter-active,
.snackbar-leave-active {
  transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.snackbar-enter-from,
.snackbar-leave-to {
  opacity: 0;
  transform: translateY(12px) scale(0.98);
}

.snackbar-leave-to {
  margin-top: -0.6rem;
}

@media (max-width: 576px) {
  .snackbar-queue {
    bottom: 5.5rem;
  }
}

[data-theme="dark"] .snackbar {
  background: #161b22 !important;
  border-color: #30363d !important;
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.5) !important;
}

[data-theme="dark"] .snackbar__title {
  color: #e6edf3 !important;
}

[data-theme="dark"] .snackbar__msg {
  color: #8b949e !important;
}
</style>
