<script setup>
import { ref } from 'vue'
import BoxAccordion from '../components/BoxAccordion.vue'

// Данные по коробкам
const boxTypes = ref([
  {
    id: 1,
    title: 'Коробка типа 1 (Квадратная)',
    shortDesc: 'Текст карточки. Описания, пояснения и т.д.',
    longDesc: 'Здесь может быть более подробное описание. Возможно, рекомендации и т.д.',
    length: 50,
    width: 50,
    height: 50, // 50x50 - Квадрат
    weight: 10,
    isOpen: true // Первая открыта со старта
  },
  {
    id: 2,
    title: 'Коробка типа 2 (Вытянутая)',
    shortDesc: 'Текст карточки. Описания, пояснения и т.д.',
    longDesc: 'Спецификации для коробки типа 2. Длинная и невысокая.',
    length: 100,
    width: 100, 
    height: 30, // 100x30 - Прямоугольник (широкая)
    weight: 5,
    isOpen: false
  },
  {
    id: 3,
    title: 'Коробка типа 3 (Плоская)',
    shortDesc: 'Текст карточки. Описания, пояснения и т.д.',
    longDesc: 'Спецификации для коробки типа 3. Особо прочная тара.',
    length: 120,
    width: 80,
    height: 120, // 80x120 - Прямоугольник (высокая)
    weight: 15,
    isOpen: false
  }
])
</script>

<template>
  <div class="guide-page">
    
    <!-- Шапка страницы -->
    <section class="guide-hero">
      <h1 class="page-title">Справочник коробок</h1>
      
      <div class="hero-content">
        <div class="floating-icon left">📦</div>
        
        <p class="hero-text">
          Здесь вы можете ознакомиться с типами коробок, их свойствами и особенностями 
        </p>
        
        <div class="floating-icon right">📦</div>
      </div>
    </section>

    <!-- Список аккордеонов -->
    <section class="types-section">
      <h2 class="section-title">Доступные типы</h2>
      
      <div class="accordion-list">
        <BoxAccordion 
          v-for="box in boxTypes" 
          :key="box.id"
          :title="box.title"
          :short-desc="box.shortDesc"
          :long-desc="box.longDesc"
          :length="box.length"
          :width="box.width"
          :height="box.height"
          :weight="box.weight"
          :initially-open="box.isOpen"
        />
      </div>
    </section>

  </div>
</template>

<style scoped>
.guide-page {
  display: flex;
  flex-direction: column;
  align-items: center;
  max-width: 900px;
  margin: 0 auto;
}

.guide-hero {
  text-align: center;
  margin-bottom: 60px;
  width: 100%;
}

.page-title {
  color: #FFD700;
  font-size: 48px;
  margin-bottom: 40px;
}

.hero-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: relative;
}

.hero-text {
  color: #e0e0e0;
  font-size: 16px;
  line-height: 1.6;
  max-width: 500px;
  margin: 0 auto;
  text-align: center;
}

.floating-icon {
  font-size: 80px;
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  filter: drop-shadow(0 10px 20px rgba(0,0,0,0.5));
}
.floating-icon.left { left: 0; transform: translateY(-50%) rotate(-15deg); }
.floating-icon.right { right: 0; transform: translateY(-50%) rotate(15deg); }

.types-section {
  width: 100%;
}

.section-title {
  color: #FFD700;
  font-size: 32px;
  text-align: center;
  margin-bottom: 40px;
}

.accordion-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
</style>