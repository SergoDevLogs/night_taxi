<script setup lang="ts">
import { ref } from 'vue';
import FloatingBoxes from '@/components/brand/FloatingBoxes.vue';
import BoxAccordion from '@/components/BoxAccordion.vue'; // Твой компонент карточки

const boxTypes = ref([
  {
    id: 1,
    title: 'Коробка типа 1 (Квадратная)',
    shortDesc: 'Стандартная тара для небольших грузов.',
    longDesc: 'Идеально подходит для отправки книг, сувениров и мелкой электроники.',
    length: 50,
    width: 50,
    height: 50,
    weight: 10,
    isOpen: true
  },
  {
    id: 2,
    title: 'Коробка типа 2 (Вытянутая)',
    shortDesc: 'Длинная коробка для специфичных товаров.',
    longDesc: 'Разработана специально для транспортировки чертежей, плакатов и т.д.',
    length: 100,
    width: 100,
    height: 30,
    weight: 5,
    isOpen: false
  }
]);
</script>

<template>
  <div class="guide-view container-page">
    
    <!-- Секция шапки с параллакс-коробками по бокам -->
    <section class="guide-hero">
      <FloatingBoxes side="left" :width="200" :height="200" :parallax="0.15" />
      
      <div class="guide-hero__content">
        <h1>Справочник коробок</h1>
        <p>
          Здесь собраны все доступные типы и размеры тары. Алгоритм подбирает 
          оптимальный вариант упаковки, опираясь на эти параметры.
        </p>
      </div>

      <FloatingBoxes side="right" :width="200" :height="200" :parallax="0.15" />
    </section>

    <!-- Секция со списком коробок -->
    <section class="guide-list">
      <h2>Доступные типы</h2>
      <div class="accordion-list">
        <BoxAccordion 
          v-for="box in boxTypes" 
          :key="box.id"
          v-bind="box"
        />
      </div>
    </section>

  </div>
</template>

<style scoped>
.guide-view {
  padding-block: 40px;
}

.guide-hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 64px;
  text-align: center;
}

.guide-hero__content {
  max-width: 600px;
  margin: 0 auto;
}

.guide-hero__content h1 {
  color: var(--color-accent);
  margin-bottom: 16px;
}

.guide-hero__content p {
  color: var(--color-text-muted);
  line-height: 1.6;
}

.guide-list {
  max-width: 800px;
  margin: 0 auto;
}

.guide-list h2 {
  color: var(--color-accent);
  margin-bottom: 24px;
  text-align: center;
}

.accordion-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
</style>