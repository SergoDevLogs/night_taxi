<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = withDefaults(
  defineProps<{
    side?: 'left' | 'right';
    width?: number;
    height?: number;
    parallax?: number;
    parallaxMax?: number;
  }>(),
  {
    side: 'left',
    width: 220,
    height: 220,
    parallax: 0.2,
    parallaxMax: 260,
  },
);

const scrollY = ref(0);

function onScroll() {
  scrollY.value = window.scrollY;
}

onMounted(() => {
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });
});

onBeforeUnmount(() => {
  window.removeEventListener('scroll', onScroll);
});

const offset = computed(() => {
  const raw = scrollY.value * props.parallax;
  const clamped = Math.min(raw, props.parallaxMax);
  return props.side === 'left' ? -clamped : clamped;
});

const rootStyle = computed(() => ({
  width: `${props.width}px`,
  height: `${props.height}px`,
  transform: `translateY(${offset.value}px)`,
}));
</script>

<template>
  <div
    class="floating-boxes"
    :class="`floating-boxes--${side}`"
    :style="rootStyle"
    aria-hidden="true"
  >
    <svg
      v-if="side === 'left'"
      width="100%"
      height="100%"
      viewBox="0 0 249 247"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      preserveAspectRatio="xMidYMid meet"
    >
      <path
        d="M68.7684 60.7854L127.681 107.117L109.651 192.906L50.7386 146.574L68.7684 60.7854Z"
        fill="#FFD700"
      />
      <path
        d="M127.681 107.117L200.331 88.0848L182.301 173.874L109.651 192.906L127.681 107.117Z"
        fill="#FFD700"
      />
      <path
        d="M127.681 107.117L200.33 88.0848L141.418 41.7536L68.7686 60.7854L127.681 107.117Z"
        fill="#FFD700"
      />
    </svg>

    <svg
      v-else
      width="100%"
      height="100%"
      viewBox="0 0 216 206"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      preserveAspectRatio="xMidYMid meet"
    >
      <path
        d="M85.2168 41.0257L36.7898 91.8483L16.6936 85.7928L65.1206 34.9702L85.2168 41.0257Z"
        fill="#FFD700"
        stroke="black"
        stroke-width="3"
        stroke-linecap="round"
        stroke-linejoin="round"
      />
      <path
        d="M155.191 49.0276L85.2172 41.0257L96.6946 23.5514L166.669 31.5533L155.191 49.0276Z"
        fill="#FFD700"
        stroke="black"
        stroke-width="3"
        stroke-linecap="round"
        stroke-linejoin="round"
      />
      <path
        d="M36.7897 91.8483L106.764 99.8502L155.191 49.0276L85.2167 41.0257L36.7897 91.8483Z"
        fill="#FFD700"
        stroke="black"
        stroke-width="3"
        stroke-linecap="round"
        stroke-linejoin="round"
      />
    </svg>
  </div>
</template>

<style scoped>
.floating-boxes {
  display: block;
  pointer-events: none;
  user-select: none;
  flex-shrink: 0;
  will-change: transform;
}
</style>