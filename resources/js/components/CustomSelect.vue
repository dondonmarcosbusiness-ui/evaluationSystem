<template>
  <div ref="containerRef" class="custom-select-container">
    <div
      ref="triggerRef"
      class="custom-select-trigger"
      :class="{
        active: isOpen,
        'has-value': modelValue,
        disabled: disabled,
      }"
      @click="toggleDropdown"
    >
      <span class="selected-text">{{ selectedLabel || placeholder }}</span>
      <i class="fas fa-chevron-down arrow" :class="{ rotated: isOpen }"></i>
    </div>

    <Teleport to="body">
      <transition name="dropdown-fade">
        <ul
          v-if="isOpen"
          ref="dropdownRef"
          class="custom-select-options custom-select-options-teleported"
          :style="dropdownStyle"
        >
          <li
            v-for="option in normalizedOptions"
            :key="option.value"
            :class="{
              selected: modelValue === option.value,
              disabled: option.disabled,
            }"
            @click="!option.disabled && selectOption(option)"
          >
            <span class="text-truncate" style="flex: 1; padding-right: 10px;">{{ option.label }}</span>
            <i v-if="modelValue === option.value" class="fas fa-check check-icon flex-shrink-0"></i>
          </li>
        </ul>
      </transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch, onUnmounted, nextTick } from "vue";

const props = defineProps({
  modelValue: [String, Number],
  options: {
    type: Array,
    required: true,
  },
  placeholder: {
    type: String,
    default: "Select an option",
  },
  disabled: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:modelValue", "change"]);

const isOpen = ref(false);
const containerRef = ref(null);
const triggerRef = ref(null);
const dropdownRef = ref(null);
const dropdownStyle = ref({});

const normalizedOptions = computed(() => {
  return props.options.map((opt) => {
    if (typeof opt === "string") {
      return { label: opt, value: opt };
    }
    return opt;
  });
});

const selectedLabel = computed(() => {
  const selected = normalizedOptions.value.find((opt) => opt.value === props.modelValue);
  return selected ? selected.label : "";
});

function updateDropdownPosition() {
  const trigger = triggerRef.value;
  if (!trigger) return;

  const rect = trigger.getBoundingClientRect();
  const menuHeight = dropdownRef.value?.offsetHeight ?? 120;
  const spaceBelow = window.innerHeight - rect.bottom;
  const openAbove = spaceBelow < menuHeight + 12 && rect.top > spaceBelow;

  dropdownStyle.value = {
    position: "fixed",
    top: openAbove ? `${rect.top - menuHeight - 8}px` : `${rect.bottom + 8}px`,
    left: `${rect.left}px`,
    width: `${rect.width}px`,
    minWidth: `${Math.max(rect.width, 160)}px`,
    zIndex: 9999,
  };
}

function toggleDropdown() {
  if (props.disabled) return;
  isOpen.value = !isOpen.value;
}

function closeDropdown() {
  isOpen.value = false;
}

function selectOption(option) {
  emit("update:modelValue", option.value);
  emit("change", option.value);
  isOpen.value = false;
}

function handleDocumentClick(event) {
  if (!isOpen.value) return;
  const container = containerRef.value;
  const dropdown = dropdownRef.value;
  if (container?.contains(event.target) || dropdown?.contains(event.target)) return;
  closeDropdown();
}

watch(isOpen, async (open) => {
  if (open) {
    await nextTick();
    updateDropdownPosition();
    await nextTick();
    updateDropdownPosition();
    document.addEventListener("click", handleDocumentClick);
    window.addEventListener("scroll", updateDropdownPosition, true);
    window.addEventListener("resize", updateDropdownPosition);
  } else {
    document.removeEventListener("click", handleDocumentClick);
    window.removeEventListener("scroll", updateDropdownPosition, true);
    window.removeEventListener("resize", updateDropdownPosition);
  }
});

onUnmounted(() => {
  document.removeEventListener("click", handleDocumentClick);
  window.removeEventListener("scroll", updateDropdownPosition, true);
  window.removeEventListener("resize", updateDropdownPosition);
});
</script>

<style scoped>
.custom-select-container {
  position: relative;
  width: 100%;
  user-select: none;
}

.custom-select-trigger {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.5rem 1rem;
  background: var(--bg-card);
  border: 1px solid #0a278a;
  border-radius: 50px;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  font-weight: 600;
  color: var(--text-dark);
  min-height: 38px;
}

.custom-select-trigger.disabled {
  opacity: 0.5;
  cursor: not-allowed;
  background: var(--bg-light);
}

.custom-select-trigger:hover {
  border-color: var(--primary);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.custom-select-trigger.active {
  border-color: var(--primary);
  box-shadow:
    0 0 0 4px rgba(10, 39, 138, 0.08),
    0 8px 20px rgba(0, 0, 0, 0.06);
  transform: translateY(-1px);
}

.selected-text {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.arrow {
  font-size: 0.75rem;
  transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
  margin-left: 1rem;
  opacity: 0.5;
}

.arrow.rotated {
  transform: rotate(-180deg);
}

/* Dropdown Menu (teleported to body) */
.custom-select-options {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  padding: 0.5rem !important;
  margin: 0;
  list-style: none;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
  backdrop-filter: blur(25px);
  -webkit-backdrop-filter: blur(25px);
  max-height: 300px;
  overflow-y: auto;
  overflow-x: hidden;
  box-sizing: border-box;
}

.custom-select-options-teleported {
  position: fixed;
}

.custom-select-options li {
  padding: 0.6rem 1rem;
  margin-bottom: 0.2rem;
  border-radius: 0.5rem;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  font-size: 0.9rem;
  font-weight: 500;
  color: var(--text-main);
  white-space: nowrap;
}

.custom-select-options li:last-child {
  margin-bottom: 0;
}

.custom-select-options li.disabled {
  opacity: 0.5;
  cursor: not-allowed;
  background: transparent !important;
  color: var(--text-muted) !important;
}

.custom-select-options li:hover {
  background: rgba(10, 39, 138, 0.05);
  color: var(--primary);
}

.custom-select-options li.selected {
  background: #0a278a;
  color: #ffffff;
  font-weight: 600;
}

.check-icon {
  font-size: 0.8rem;
  opacity: 0.9;
}

/* Transitions */
.dropdown-fade-enter-active,
.dropdown-fade-leave-active {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.dropdown-fade-enter-from,
.dropdown-fade-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

[data-theme="dark"] .custom-select-options {
  background: rgba(30, 41, 59, 0.95);
  border-color: rgba(255, 255, 255, 0.05);
}

[data-theme="dark"] .custom-select-options li:hover {
  background: rgba(255, 255, 255, 0.05);
}
</style>
