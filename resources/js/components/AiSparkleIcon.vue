<template>
  <svg
    class="ai-sparkle-icon"
    :class="customClass"
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    aria-hidden="true"
  >
    <defs>
      <linearGradient :id="gradientId" x1="2" y1="2" x2="22" y2="22" gradientUnits="userSpaceOnUse">
        <stop stop-color="#4285F4" />
        <stop offset="0.35" stop-color="#7B6FD6" />
        <stop offset="0.65" stop-color="#D96570" />
        <stop offset="1" stop-color="#F2994A" />
      </linearGradient>
    </defs>
    <path
      :fill="variant === 'white' ? '#ffffff' : `url(#${gradientId})`"
      d="M12 2.25c.35 4.55 3.2 7.9 7.75 8.25-4.55.35-7.4 3.7-7.75 8.25-.35-4.55-3.2-7.9-7.75-8.25 4.55-.35 7.4-3.7 7.75-8.25Z"
    />
  </svg>
</template>

<script setup>
import { useId } from "vue";

defineProps({
  size: { type: [Number, String], default: 24 },
  variant: { type: String, default: "gradient" },
  customClass: { type: String, default: "" },
});

const gradientId = useId();
</script>

<style scoped>
.ai-sparkle-icon {
  display: block;
  flex-shrink: 0;
}
</style>
