<script setup>
defineProps({
  icon: String,
  title: String,
  description: String
})
</script>

<template>
  <div class="card">
    <div class="icon">{{ icon }}</div>
    <h3 class="card-title">{{ title }}</h3>
    <p class="card-desc">{{ description }}</p>
  </div>
</template>

<style scoped>
.card {
  background-color: #2c2c2c; 
  border-radius: 16px;
  padding: 24px;
  flex: 1; 
  transition: transform 0.2s;
}

.card:hover {
  transform: translateY(-5px);
}

.icon {
  font-size: 24px;
  color: #FFD700; 
  margin-bottom: 12px;
}

.card-title {
  color: #FFD700;
  font-size: 18px;
  margin: 0 0 8px 0;
}

.card-desc {
  color: #a0a0a0;
  font-size: 14px;
  line-height: 1.4;
  margin: 0;
}
</style>