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

    <transition name="dropdown-fade">
      <ul
        v-if="isOpen"
        ref="dropdownRef"
        class="custom-select-options"
        :class="{ 'drop-up': dropUp }"
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
const dropUp = ref(false);
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

function updateDropdownDirection() {
  // No hardcoded coordinates — the menu is anchored to the trigger in CSS.
  // We only decide whether it opens below or above based on viewport space.
  const trigger = triggerRef.value;
  if (!trigger) return;

  const rect = trigger.getBoundingClientRect();
  const menuHeight = dropdownRef.value?.offsetHeight ?? 120;
  const spaceBelow = window.innerHeight - rect.bottom;
  dropUp.value = spaceBelow < menuHeight && rect.top > spaceBelow;
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
    dropUp.value = false;
    await nextTick();
    updateDropdownDirection();
    if (props.searchable && searchInputRef.value) {
      searchInputRef.value.focus();
    }
    document.addEventListener("click", handleDocumentClick);
  } else {
    document.removeEventListener("click", handleDocumentClick);
  }
});

onUnmounted(() => {
  document.removeEventListener("click", handleDocumentClick);
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
  padding: 10px 16px;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  font-weight: 500;
  font-size: 14px;
  color: var(--text-dark);
  min-height: 40px;
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
  box-shadow: 0 0 0 3px rgba(25, 25, 112, 0.15);
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

/* Dropdown Menu (anchored to the trigger — no hardcoded coordinates) */
.custom-select-options {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  right: 0;
  min-width: 160px;
  z-index: 2000;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 12px;
  padding: 8px !important;
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

.custom-select-options.drop-up {
  top: auto;
  bottom: calc(100% + 8px);
}

.custom-select-options li {
  padding: 8px 12px;
  margin-bottom: 4px;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  font-size: 14px;
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

.custom-select-options.drop-up.dropdown-fade-enter-from,
.custom-select-options.drop-up.dropdown-fade-leave-to {
  transform: translateY(10px);
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
  padding: 10px 16px;
  border: 1px solid var(--border-light);
  border-radius: 8px;
  background: var(--bg-light);
  font-size: 14px;
  font-weight: 500;
  outline: none;
  box-sizing: border-box;
  min-height: 40px;
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

[data-theme="dark"] .custom-select-trigger {
  background: transparent !important;
  color: #e6edf3 !important;
}

[data-theme="dark"] .custom-select-trigger .selected-text {
  color: #e6edf3 !important;
}

[data-theme="dark"] .custom-select-trigger .arrow {
  color: #b1bac4 !important;
  opacity: 1 !important;
}

[data-theme="dark"] .custom-select-options {
  background: #161b22 !important;
  border-color: #30363d !important;
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.5) !important;
}

[data-theme="dark"] .custom-select-options li {
  color: #e6edf3 !important;
}

[data-theme="dark"] .custom-select-options li:hover {
  background: rgba(31, 111, 235, 0.18) !important;
  color: #79c0ff !important;
}

[data-theme="dark"] .custom-select-options li.selected,
[data-theme="dark"] .custom-select-options li.selected:hover {
  background: #1f6feb !important;
  color: #ffffff !important;
}

[data-theme="dark"] .custom-select-options li.selected .check-icon {
  color: #ffffff !important;
}

[data-theme="dark"] .search-input-premium {
  background: #0d1117 !important;
  border-color: #30363d !important;
  color: #e6edf3 !important;
}

[data-theme="dark"] .search-input-premium::placeholder {
  color: #8b949e !important;
  opacity: 1 !important;
}

[data-theme="dark"] .search-input-premium:focus {
  border-color: #1f6feb !important;
  background: #0d1117 !important;
}
</style>
