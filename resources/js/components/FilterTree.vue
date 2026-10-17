<template>
  <div class="filter-tree">
    <div v-for="group in groups" :key="group.key" class="tree-group">
      <button type="button" class="tree-parent" @click="toggleGroup(group.key)">
        <i class="fas fa-chevron-right tree-chevron" :class="{ 'is-open': isOpen(group.key) }"></i>
        <span class="tree-parent-label">{{ group.label }}</span>
        <span class="tree-parent-value">{{ group.valueLabel }}</span>
      </button>

      <div v-if="isOpen(group.key)" class="tree-children">
        <template v-for="opt in group.options" :key="String(opt.value)">
          <div class="tree-option" :class="{ 'is-selected': group.value === opt.value }">
            <button type="button" class="tree-option-main" @click="choose(group, opt)">
              <span class="tree-radio" :class="{ 'is-on': group.value === opt.value }"></span>
              <span class="tree-option-label">{{ opt.label }}</span>
            </button>
            <button
              v-if="opt.children && opt.children.length"
              type="button"
              class="tree-expand"
              :title="`${opt.children.length} items`"
              @click.stop="toggleNested(group.key, opt.value)"
            >
              <i
                class="fas fa-chevron-right tree-chevron"
                :class="{ 'is-open': isNestedOpen(group.key, opt.value) }"
              ></i>
              <span class="tree-nested-count">{{ opt.children.length }}</span>
            </button>
          </div>

          <div
            v-if="opt.children && opt.children.length && isNestedOpen(group.key, opt.value)"
            class="tree-children nested"
          >
            <div
              v-for="child in opt.children"
              :key="String(child.value)"
              class="tree-option"
              :class="{ 'is-selected': group.value === child.value }"
            >
              <button type="button" class="tree-option-main" @click="choose(group, child)">
                <span class="tree-radio" :class="{ 'is-on': group.value === child.value }"></span>
                <span class="tree-option-label">{{ child.label }}</span>
              </button>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";

const props = defineProps({
  groups: {
    type: Array,
    required: true,
  },
  defaultOpen: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["select"]);

const openGroups = ref(new Set(props.defaultOpen));
const openNestedKeys = ref(new Set());

function isOpen(key) {
  return openGroups.value.has(key);
}

function toggleGroup(key) {
  const next = new Set(openGroups.value);
  if (next.has(key)) {
    next.delete(key);
  } else {
    next.add(key);
  }
  openGroups.value = next;
}

function nestedKey(groupKey, value) {
  return `${groupKey}:${value}`;
}

function isNestedOpen(groupKey, value) {
  return openNestedKeys.value.has(nestedKey(groupKey, value));
}

function toggleNested(groupKey, value) {
  const key = nestedKey(groupKey, value);
  const next = new Set(openNestedKeys.value);
  if (next.has(key)) {
    next.delete(key);
  } else {
    next.add(key);
  }
  openNestedKeys.value = next;
}

function choose(group, option) {
  emit("select", {
    key: option.selectKey || group.key,
    value: option.value,
    payload: option.payload || null,
  });
}
</script>

<style scoped>
.filter-tree {
  display: flex;
  flex-direction: column;
  gap: 2px;
  max-height: 320px;
  overflow-y: auto;
  padding-right: 6px;
}

.tree-group {
  border-bottom: 1px solid var(--border-light);
}

.tree-group:last-child {
  border-bottom: 0;
}

.tree-parent {
  display: flex;
  align-items: center;
  gap: 9px;
  width: 100%;
  padding: 9px 4px;
  border: 0;
  background: none;
  cursor: pointer;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--text-dark);
  text-align: left;
}

.tree-parent:hover {
  color: var(--primary);
}

.tree-chevron {
  font-size: 0.62rem;
  color: var(--text-muted);
  transition: transform 0.15s ease;
}

.tree-chevron.is-open {
  transform: rotate(90deg);
}

.tree-parent-label {
  flex: 1;
}

.tree-parent-value {
  font-weight: 500;
  font-size: 0.72rem;
  color: var(--primary);
  max-width: 240px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tree-children {
  display: flex;
  flex-direction: column;
  gap: 1px;
  padding: 0 0 8px 18px;
}

.tree-children.nested {
  padding-left: 28px;
  padding-bottom: 6px;
}

.tree-option {
  display: flex;
  align-items: center;
  gap: 2px;
}

.tree-option-main {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  min-width: 0;
  padding: 6px 8px;
  border: 0;
  border-radius: 6px;
  background: none;
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--text-dark);
  text-align: left;
  cursor: pointer;
  transition: background-color 0.12s ease;
}

.tree-option-main:hover {
  background: rgba(25, 25, 112, 0.05);
}

.tree-option.is-selected > .tree-option-main {
  background: rgba(25, 25, 112, 0.1);
  color: var(--primary);
  font-weight: 600;
}

.tree-radio {
  width: 11px;
  height: 11px;
  border-radius: 50%;
  border: 1.5px solid var(--border-light);
  flex-shrink: 0;
  transition: all 0.12s ease;
}

.tree-radio.is-on {
  border-color: var(--primary);
  background: var(--primary);
  box-shadow: inset 0 0 0 2px var(--bg-card);
}

.tree-option-label {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tree-expand {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border: 0;
  background: none;
  padding: 4px 8px;
  cursor: pointer;
  color: var(--text-muted);
}

.tree-expand:hover {
  color: var(--primary);
}

.tree-nested-count {
  font-size: 0.68rem;
  font-weight: 600;
}

[data-theme="dark"] .tree-parent,
[data-theme="dark"] .tree-option-main {
  color: #e6edf3;
}

[data-theme="dark"] .tree-parent:hover {
  color: #79c0ff;
}

[data-theme="dark"] .tree-option-main:hover {
  background: rgba(121, 192, 255, 0.08);
}

[data-theme="dark"] .tree-option.is-selected > .tree-option-main {
  background: rgba(121, 192, 255, 0.16);
  color: #79c0ff;
}

[data-theme="dark"] .tree-radio.is-on {
  background: #79c0ff;
  border-color: #79c0ff;
  box-shadow: inset 0 0 0 2px #0d1117;
}

[data-theme="dark"] .tree-parent-value {
  color: #79c0ff;
}
</style>
