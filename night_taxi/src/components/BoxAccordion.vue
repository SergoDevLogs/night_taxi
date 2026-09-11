<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  title: String,
  shortDesc: String,
  longDesc: String,
  length: Number,
  width: Number,
  height: Number,
  weight: Number,
  initiallyOpen: {
    type: Boolean,
    default: false
  }
})

// Локальное состояние открытия (по умолчанию берем из пропа)
const isOpen = ref(props.initiallyOpen)

const toggle = () => {
  isOpen.value = !isOpen.value
}

// Математика для чертежа (макс размер 140px)
const MAX_DRAWING_SIZE = 140
const boxStyles = computed(() => {
  const maxDim = Math.max(props.width, props.height)
  const scale = MAX_DRAWING_SIZE / maxDim
  return {
    width: `${props.width * scale}px`,
    height: `${props.height * scale}px`
  }
})
</script>

<template>
  <div class="accordion-item" :class="{ 'is-open': isOpen }">
    
    <!-- Шапка (кликабельная) -->
    <div class="accordion-header" @click="toggle">
      <div class="header-text">
        <h3 class="title">{{ title }}</h3>
        <p class="desc">{{ shortDesc }}</p>
      </div>
      <button class="toggle-btn">
        <span class="arrow" :class="{ 'arrow-up': isOpen }">▼</span>
      </button>
    </div>

    <!-- Раскрывающееся тело -->
    <Transition name="expand">
      <div class="accordion-body" v-show="isOpen">
        <div class="body-content">
          
          <div class="info-block">
            <p class="long-desc">{{ longDesc }}</p>
            <div class="specs">
              <div class="spec-item">
                <span class="spec-label">Размеры (ДхШхВ):</span>
                <span class="spec-value">{{ length }} × {{ width }} × {{ height }} см</span>
              </div>
              <div class="spec-item">
                <span class="spec-label">Макс. вес:</span>
                <span class="spec-value">{{ weight }} кг</span>
              </div>
            </div>
          </div>
          
          <!-- Динамический чертеж -->
          <div class="schema-placeholder">
            <div class="schema-box">
              <div class="label-vertical">
                <span class="val">{{ height }}</span>
                <span class="arr">↕</span>
              </div>
              
              <div class="box-shape" :style="boxStyles"></div>
              
              <div class="label-horizontal">
                <span class="arr">↔</span>
                <span class="val">{{ width }}</span>
              </div>
            </div>
          </div>

        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.accordion-item {
  background-color: #2c2c2c;
  border-radius: 16px;
  padding: 24px;
  margin-bottom: 20px;
  transition: border-color 0.3s;
  border: 1px solid transparent;
}
.accordion-item.is-open {
  border-color: #555;
}

.accordion-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  cursor: pointer;
}
.title {
  color: #FFD700;
  margin: 0 0 8px 0;
  font-size: 20px;
}
.desc {
  color: #a0a0a0;
  margin: 0;
  font-size: 14px;
  max-width: 85%;
}

.toggle-btn {
  background: #FFD700;
  border: none;
  border-radius: 50%;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #1a1a1a;
  font-size: 12px;
  flex-shrink: 0;
}
.arrow {
  transition: transform 0.3s ease;
}
.arrow-up {
  transform: rotate(180deg);
}

.accordion-body {
  margin-top: 24px;
  padding-top: 24px;
  border-top: 1px solid #444;
}

.body-content {
  display: flex;
  justify-content: space-between;
  gap: 40px;
}

.info-block {
  flex: 1;
}
.long-desc {
  color: #e0e0e0;
  font-size: 15px;
  line-height: 1.5;
  margin: 0 0 24px 0;
}

.specs {
  background: #1a1a1a;
  border-radius: 12px;
  padding: 16px;
}
.spec-item {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
}
.spec-item:last-child { margin-bottom: 0; }
.spec-label { color: #a0a0a0; font-size: 14px; }
.spec-value { color: #FFD700; font-weight: bold; font-size: 14px; }

.schema-placeholder {
  flex: 0 0 200px;
  display: flex;
  justify-content: center;
  align-items: center;
}
.schema-box {
  position: relative;
  display: flex;
}
.box-shape {
  border: 2px solid white;
  background: rgba(255, 215, 0, 0.05);
  border-radius: 4px;
  transition: all 0.4s ease;
}

.label-vertical {
  position: absolute;
  right: -30px;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  flex-direction: column;
  align-items: center;
  color: #a0a0a0;
  font-size: 12px;
}
.label-horizontal {
  position: absolute;
  bottom: -30px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  flex-direction: column;
  align-items: center;
  color: #a0a0a0;
  font-size: 12px;
}
.val { color: white; font-weight: bold; margin-bottom: 2px; }

/* Анимация (Vue Transition) */
.expand-enter-active, .expand-leave-active {
  transition: all 0.3s ease;
  max-height: 400px;
  opacity: 1;
  overflow: hidden;
}
.expand-enter-from, .expand-leave-to {
  max-height: 0;
  opacity: 0;
  margin-top: 0;
  padding-top: 0;
  border-top-color: transparent;
}
</style>