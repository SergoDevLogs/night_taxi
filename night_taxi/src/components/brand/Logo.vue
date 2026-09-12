<script setup lang="ts">
withDefaults(defineProps<{ size?: number }>(), { size: 36 });
</script>

<template>
  <svg
    :width="size"
    :height="size"
    viewBox="0 0 36 36"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    aria-label="ФитТара"
  >
    <rect width="36" height="36" rx="8" fill="var(--color-accent)" />
    <path
      d="M10 12 L26 12 L26 26 L10 26 Z M10 12 L18 6 L26 12 M10 26 L18 20 L26 26 M18 6 L18 20"
      stroke="white"
      stroke-width="2"
      stroke-linejoin="round"
      fill="none"
    />
  </svg>
</template>

<style scoped>
svg { display: block; flex-shrink: 0; }
</style>