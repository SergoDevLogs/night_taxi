<script setup>
import { ref } from 'vue'
import FeatureCard from '../components/FeatureCard.vue'

const cardsData = ref([
  { id: 1, icon: '🌙', title: 'Карточка 1', description: 'Текст карточки. Описания, пояснения и т.д.' },
  { id: 2, icon: '◬', title: 'Карточка 2', description: 'Текст карточки. Описания, пояснения и т.д.' },
  { id: 3, icon: '✦', title: 'Карточка 3', description: 'Текст карточки. Описания, пояснения и т.д.' }
])
</script>

<template>
  <div>
    <!-- ГЛАВНЫЙ БЛОК (HERO) -->
    <main class="hero-section">
      <div class="hero-text">
        <h1>Добро<br>пожаловать<br>в ФитТара!</h1>
        <p>Текст параграфа.<br>Описание, пояснение, дополнительная информация</p>
        <button class="btn-primary">Перейти к упаковке</button>
      </div>
      
      <div class="hero-image">
        <div class="image-placeholder">📦</div>
      </div>
    </main>

    <!-- БЛОК С КАРТОЧКАМИ -->
    <section class="info-section">
      <h2 class="section-title">О системе</h2>
      <div class="cards-grid">
        <FeatureCard 
          v-for="card in cardsData" 
          :key="card.id"
          :icon="card.icon"
          :title="card.title"
          :description="card.description"
        />
      </div>
    </section>
  </div>
</template>

<style scoped>
/* Оставили только стили контента */
.hero-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 100px;
}

.hero-text h1 {
  color: #FFD700;
  font-size: 56px;
  line-height: 1.1;
  margin: 0 0 24px 0;
}

.hero-text p {
  font-size: 18px;
  color: #e0e0e0;
  margin-bottom: 40px;
  line-height: 1.5;
}

.btn-primary {
  background-color: #FFD700;
  color: #1a1a1a;
  border: none;
  border-radius: 24px;
  padding: 16px 32px;
  font-size: 16px;
  font-weight: bold;
  cursor: pointer;
  transition: background-color 0.2s;
}

.btn-primary:hover {
  background-color: #e6c200;
}

.image-placeholder {
  width: 350px;
  height: 250px;
  background: linear-gradient(135deg, #2c2c2c, #1a1a1a);
  border: 2px solid #333;
  border-radius: 20px;
  transform: rotate(-10deg);
  display: flex;
  justify-content: center;
  align-items: center;
  font-size: 80px;
  box-shadow: 0 20px 40px rgba(0,0,0,0.5);
}

.section-title {
  color: #FFD700;
  font-size: 32px;
  margin-bottom: 32px;
}

.cards-grid {
  display: flex;
  gap: 24px;
}
</style>