<template>
  <div class="pw-wrap">
    <input ref="inputEl" v-model="value" v-bind="$attrs" :type="visible ? 'text' : 'password'" class="pw-input" />
    <button
      type="button"
      class="pw-eye"
      :aria-label="visible ? 'Hide password' : 'Show password'"
      :aria-pressed="visible"
      :tabindex="disabled ? -1 : 0"
      @click="toggle"
    >
      <i class="fas" :class="visible ? 'fa-eye-slash' : 'fa-eye'" aria-hidden="true"></i>
    </button>
  </div>
</template>

<script setup>
import { ref, computed, useAttrs } from "vue";

defineOptions({ inheritAttrs: false });

const props = defineProps({
  modelValue: { type: [String, Number], default: "" },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

const attrs = useAttrs();
const inputEl = ref(null);
const visible = ref(false);

const value = computed({
  get: () => props.modelValue,
  set: (val) => emit("update:modelValue", val),
});

const disabled = computed(() => props.disabled === true || attrs.disabled === "" || attrs.disabled === true);

function toggle() {
  if (disabled.value) return;
  visible.value = !visible.value;
}

function focus() {
  inputEl.value?.focus();
}

defineExpose({ focus });
</script>

<style scoped>
.pw-wrap {
  position: relative;
  display: block;
  width: 100%;
  flex: 1 1 auto;
  min-width: 0;
}

.pw-input {
  display: block;
  width: 100%;
  padding-right: 2.6rem !important;
}

.pw-eye {
  position: absolute;
  top: 0;
  right: 0.15rem;
  bottom: 0;
  width: 2.35rem;
  border: none;
  background: transparent;
  color: var(--text-muted, #59636e);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
  z-index: 5;
  transition: color 0.15s ease;
}
.pw-eye:hover,
.pw-eye:focus-visible {
  color: var(--text-dark, #1f2328);
  outline: none;
}

/* Keep Bootstrap's invalid feedback icon visible beside the eye */
.pw-input.is-invalid {
  padding-right: 3.6rem !important;
}
.pw-input.is-invalid ~ .pw-eye {
  right: 1.7rem;
}
</style>
