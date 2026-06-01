<template>
  <div class="dropdown language-switcher">
    <button
      class="btn btn-lang-toggle d-flex align-items-center gap-2"
      data-bs-toggle="dropdown"
      aria-expanded="false"
    >
      <i class="fas fa-globe"></i>
      <span class="d-none d-md-inline">{{ currentLang === 'en' ? 'English' : 'Filipino' }}</span>
      <i class="fas fa-chevron-down small opacity-50"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 mt-2 p-2">
      <li>
        <button 
          class="dropdown-item rounded-3 d-flex align-items-center justify-content-between" 
          :class="{ active: currentLang === 'en' }"
          @click="setLanguage('en')"
        >
          <span>English</span>
          <i v-if="currentLang === 'en'" class="fas fa-check small text-primary"></i>
        </button>
      </li>
      <li>
        <button 
          class="dropdown-item rounded-3 d-flex align-items-center justify-content-between" 
          :class="{ active: currentLang === 'tl' }"
          @click="setLanguage('tl')"
        >
          <span>Filipino</span>
          <i v-if="currentLang === 'tl'" class="fas fa-check small text-primary"></i>
        </button>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { useLanguage } from '../helpers/language';

const { currentLang, setLanguage } = useLanguage();
</script>

<style scoped>
.btn-lang-toggle {
  background: rgba(var(--primary-rgb), 0.05);
  border: 1px solid rgba(var(--primary-rgb), 0.1);
  color: var(--text-main);
  padding: 0.5rem 1rem;
  border-radius: 50px;
  font-size: 0.85rem;
  font-weight: 600;
  transition: all 0.2s ease;
}

.btn-lang-toggle:hover {
  background: rgba(var(--primary-rgb), 0.1);
  border-color: var(--primary);
}

.dropdown-item {
  font-size: 0.85rem;
  font-weight: 500;
  padding: 0.6rem 1rem;
  color: var(--text-muted);
}

.dropdown-item:hover {
  background: rgba(var(--primary-rgb), 0.05);
  color: var(--primary);
}

.dropdown-item.active {
  background: var(--primary) !important;
  color: white !important;
}

.dropdown-item.active i {
  color: white !important;
}
</style>
