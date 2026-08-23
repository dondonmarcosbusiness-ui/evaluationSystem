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
          <li v-if="searchable" class="search-input-li" @click.stop>
            <input
              ref="searchInputRef"
              v-model="searchQuery"
              type="text"
              class="search-input-premium"
              placeholder="Type to search..."
              @input="onSearchInput"
            />
          </li>
          <li
            v-for="option in visibleOptions"
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
          <li v-if="visibleOptions.length === 0" class="no-results-li">
            <span class="text-muted small fw-600">No matches found</span>
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
  searchable: {
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
const searchQuery = ref("");
const searchInputRef = ref(null);

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

const filteredOptions = computed(() => {
  if (!props.searchable || !searchQuery.value) return normalizedOptions.value;
  const q = searchQuery.value.toLowerCase();
  return normalizedOptions.value.filter((opt) => opt.label.toLowerCase().includes(q));
});

const visibleOptions = computed(() => {
  return props.searchable ? filteredOptions.value : normalizedOptions.value;
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
  if (props.searchable) {
    searchQuery.value = "";
  }
}

function onSearchInput() {
  // keep dropdown open while typing
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
    if (props.searchable && searchInputRef.value) {
      searchInputRef.value.focus();
    }
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
  border: 1px solid var(--border-color);
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
    0 0 0 4px rgba(25, 25, 112, 0.12),
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
  background: rgba(25, 25, 112, 0.08);
  color: var(--primary);
}

.custom-select-options li.selected {
  background: var(--primary);
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

.search-input-li {
  padding: 0 !important;
  margin-bottom: 0.4rem;
  position: sticky;
  top: 0;
  z-index: 1;
  background: inherit;
}

.search-input-premium {
  width: 100%;
  padding: 0.6rem 0.85rem;
  border: 1px solid var(--border-light);
  border-radius: 0.5rem;
  background: var(--bg-light);
  font-size: 0.8rem;
  font-weight: 600;
  outline: none;
  box-sizing: border-box;
}

.search-input-premium:focus {
  border-color: var(--primary);
  background: white;
}

.no-results-li {
  justify-content: center;
  padding: 1rem !important;
  cursor: default !important;
}

.no-results-li:hover {
  background: transparent !important;
  color: var(--text-muted) !important;
}

[data-theme="dark"] .custom-select-options {
  background: rgba(30, 41, 59, 0.95);
  border-color: rgba(255, 255, 255, 0.05);
}

[data-theme="dark"] .custom-select-options li:hover {
  background: rgba(255, 255, 255, 0.05);
}
</style>
