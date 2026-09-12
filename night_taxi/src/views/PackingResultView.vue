<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

// Фейковые данные со слоями
const packingResult = ref({
  order_id: 123,
  boxes_count: 1,
  total_weight: 1250,
  boxes: [
    {
      id: 1,
      box_name: 'Стандартная коробка M',
      box_length: 40,
      box_width: 30,
      steps: [
        {
          step: 1,
          sku: 'SOAP_HAND_01',
          product_name: 'Мыло ручной работы',
          length: 10,
          width: 8,
          coordinates: { x: 2, y: 2, z: 0 },
          text: 'Положите Мыло на дне коробки в левом углу.'
        },
        {
          step: 2,
          sku: 'SHAMPOO_ORG_200',
          product_name: 'Шампунь Organic',
          length: 12,
          width: 6,
          coordinates: { x: 14, y: 2, z: 0 },
          text: 'Положите Шампунь справа от мыла на дно.'
        },
        {
          step: 3,
          sku: 'GIFT_BOX_MAX',
          product_name: 'Подарочный набор (Верхний)',
          length: 22,
          width: 16,
          coordinates: { x: 2, y: 2, z: 15 },
          text: 'Положите Подарочный набор сверху на Мыло и Шампунь.'
        }
      ]
    }
  ]
})

const currentBoxIndex = ref(0)
const currentBox = computed(() => packingResult.value.boxes[currentBoxIndex.value])

const availableLayers = computed(() => {
  if (!currentBox.value) return []
  const zSet = new Set(currentBox.value.steps.map(item => item.coordinates.z ?? 0))
  return Array.from(zSet).sort((a, b) => a - b)
})

const currentLayerIndex = ref(0)
const activeZ = computed(() => availableLayers.value[currentLayerIndex.value] ?? 0)

const stepsOnCurrentLayer = computed(() => {
  if (!currentBox.value) return []
  return currentBox.value.steps.filter(item => (item.coordinates.z ?? 0) === activeZ.value)
})

const currentStepInLayerIndex = ref(0)

const currentStep = computed(() => {
  return stepsOnCurrentLayer.value[currentStepInLayerIndex.value] || null
})

// Навигация с проверкой на окончание всех товаров
const nextStep = () => {
  if (currentStepInLayerIndex.value < stepsOnCurrentLayer.value.length - 1) {
    currentStepInLayerIndex.value++
  } else if (currentLayerIndex.value < availableLayers.value.length - 1) {
    currentLayerIndex.value++
    currentStepInLayerIndex.value = 0
  } else {
    // ЕСЛИ ЭТО БЫЛ ПОСЛЕДНИЙ ТОВАР НА ПОСЛЕДНЕМ СЛОЕ — ПЕРЕХОДИМ НА УСПЕХ!
    router.push('/success')
  }
}

const prevStep = () => {
  if (currentStepInLayerIndex.value > 0) {
    currentStepInLayerIndex.value--
  } else if (currentLayerIndex.value > 0) {
    currentLayerIndex.value--
    currentStepInLayerIndex.value = stepsOnCurrentLayer.value.length - 1
  }
}

const selectLayer = (index) => {
  currentLayerIndex.value = index
  currentStepInLayerIndex.value = 0
}
</script>

<template>
  <div class="packing-result-page">
    <div class="header-row">
      <h1 class="page-title">Упаковка заказа №{{ packingResult.order_id }}</h1>
      <div class="metrics-badge">
        <span>Коробка: {{ currentBox.box_name }}</span>
      </div>
    </div>

    <!-- ПАНЕЛЬ ПЕРЕКЛЮЧЕНИЯ СЛОЕВ -->
    <div class="layers-tabs">
      <span class="layers-label">Упаковка по слоям:</span>
      <button 
        v-for="(zValue, index) in availableLayers" 
        :key="zValue"
        class="layer-tab-btn"
        :class="{ active: currentLayerIndex === index }"
        @click="selectLayer(index)"
      >
        🗂 Слой {{ index + 1 }} <small>(высота Z: {{ zValue }}см)</small>
      </button>
    </div>

    <!-- Добавили класс packer-container с отступами gap: 20px -->
    <div v-if="currentBox && currentStep" class="packer-container">
      
      <!-- ВЕРХ: Карточка текущего товара -->
      <div class="item-to-pack-card">
        <div class="step-counter">
          Слой {{ currentLayerIndex + 1 }} | Шаг {{ currentStepInLayerIndex + 1 }} из {{ stepsOnCurrentLayer.length }}
        </div>
        <div class="product-highlight">
          <h2>📦 Положите на этот слой: <span class="yellow-text">{{ currentStep.product_name }}</span></h2>
          <p class="sku-tag">SKU: {{ currentStep.sku }} | Размер: {{ currentStep.length }}×{{ currentStep.width }} см</p>
          <p class="instruction-text">👉 <strong>Инструкция:</strong> {{ currentStep.text }}</p>
        </div>
      </div>

      <!-- СЕРЕДИНА: Вид коробки сверху -->
      <div class="box-visual-section">
        <div class="box-meta">
          <h3>Вид коробки сверху (Слой №{{ currentLayerIndex + 1 }})</h3>
          <span class="fill-indicator">Товаров на этаже: {{ stepsOnCurrentLayer.length }} шт.</span>
        </div>

        <div class="box-2d-canvas">
          <div 
            v-for="(item, index) in currentBox.steps" 
            :key="item.sku"
            class="product-block"
            :class="{ 
              'on-current-layer': (item.coordinates.z ?? 0) === activeZ,
              'on-other-layer': (item.coordinates.z ?? 0) !== activeZ,
              'active-target': ((item.coordinates.z ?? 0) === activeZ) && (index === currentBox.steps.indexOf(currentStep))
            }"
            :style="{
              left: `${item.coordinates.x * 7}px`,
              top: `${item.coordinates.y * 7}px`,
              width: `${item.length * 7}px`,
              height: `${item.width * 7}px`
            }"
          >
            <span class="block-name">{{ item.product_name }}</span>
            <span class="block-z">Z: {{ item.coordinates.z ?? 0 }}см</span>
          </div>
        </div>

        <div class="canvas-legend">
          <span class="legend-item"><i class="dot current"></i> Кладется сейчас</span>
          <span class="legend-item"><i class="dot other-layer"></i> Товары на других слоях</span>
        </div>
      </div>

      <!-- НИЗ: Кнопки навигации -->
      <div class="navigation-controls">
        <button class="btn-secondary" :disabled="currentLayerIndex === 0 && currentStepInLayerIndex === 0" @click="prevStep">
          ← Назад
        </button>
        <span class="step-indicator">
          Слой {{ currentLayerIndex + 1 }} (Шаг {{ currentStepInLayerIndex + 1 }}/{{ stepsOnCurrentLayer.length }})
        </span>
        <button class="btn-primary" @click="nextStep">
          Следующий шаг →
        </button>
      </div>

    </div>
  </div>
</template>

<style scoped>
.packing-result-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
  color: white;
}

.header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.page-title {
  color: #FFD700;
  font-size: 28px;
  margin: 0;
}

.metrics-badge {
  background: #2c2c2c;
  padding: 10px 20px;
  border-radius: 12px;
  font-size: 14px;
  color: #FFD700;
  font-weight: bold;
}

.layers-tabs {
  display: flex;
  align-items: center;
  gap: 12px;
  background: #2c2c2c;
  padding: 12px 20px;
  border-radius: 14px;
}
.layers-label {
  color: #a0a0a0;
  font-size: 14px;
}
.layer-tab-btn {
  background: #1a1a1a;
  border: 1px solid #444;
  color: #a0a0a0;
  padding: 8px 16px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: bold;
  transition: all 0.2s;
}
.layer-tab-btn.active {
  background: #FFD700;
  color: #1a1a1a;
  border-color: #FFD700;
}

/* ГЛАВНЫЙ КОНТЕЙНЕР С КОРРЕКТНЫМИ ОТСТУПАМИ */
.packer-container {
  display: flex;
  flex-direction: column;
  gap: 20px; /* <--- Вот тут добавили отступы между всеми блоками */
}

.item-to-pack-card {
  background-color: #2c2c2c;
  border-radius: 16px;
  padding: 24px;
  border-left: 6px solid #FFD700;
  position: relative;
}

.step-counter {
  position: absolute;
  top: 24px;
  right: 24px;
  background: #1a1a1a;
  padding: 4px 12px;
  border-radius: 8px;
  font-size: 12px;
  color: #FFD700;
}

.product-highlight h2 { margin: 0 0 8px 0; font-size: 22px; }
.yellow-text { color: #FFD700; }
.sku-tag {
  display: inline-block;
  background: #1a1a1a;
  color: #a0a0a0;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 12px;
  margin-bottom: 12px;
}
.instruction-text { font-size: 16px; color: #e0e0e0; margin: 0; }

.box-visual-section {
  background-color: #2c2c2c;
  border-radius: 16px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.box-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.box-meta h3 { margin: 0; font-size: 18px; }
.fill-indicator { color: #a0a0a0; font-size: 14px; }

.box-2d-canvas {
  width: 100%;
  height: 350px;
  background-color: #1a1a1a;
  border: 2px solid #444;
  border-radius: 12px;
  position: relative;
  overflow: hidden;
}

.product-block {
  position: absolute;
  border-radius: 6px;
  padding: 6px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  font-size: 11px;
  box-sizing: border-box;
  transition: all 0.3s ease;
}

.block-name { font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.block-z { font-size: 9px; opacity: 0.7; }

.product-block.on-other-layer {
  background: rgba(80, 80, 80, 0.15);
  border: 1px dashed #444;
  color: #555;
  opacity: 0.3;
}

.product-block.on-current-layer {
  background: rgba(46, 204, 113, 0.2);
  border: 1px solid #2ecc71;
  color: #2ecc71;
}

.product-block.active-target {
  background: rgba(255, 215, 0, 0.3);
  border: 2px solid #FFD700;
  color: #FFD700;
  box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
  z-index: 5;
}

.canvas-legend {
  display: flex;
  gap: 24px;
  font-size: 13px;
  color: #a0a0a0;
  justify-content: center;
}
.legend-item { display: flex; align-items: center; gap: 8px; }
.dot { width: 10px; height: 10px; border-radius: 50%; }
.dot.current { background: #FFD700; }
.dot.other-layer { background: #555; }

.navigation-controls {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background-color: #2c2c2c;
  padding: 16px 24px;
  border-radius: 16px;
}

.step-indicator { color: #a0a0a0; font-size: 14px; }

.btn-primary, .btn-secondary {
  padding: 12px 24px;
  border-radius: 12px;
  font-weight: bold;
  cursor: pointer;
  border: none;
}
.btn-primary { background: #FFD700; color: #1a1a1a; }
.btn-secondary { background: transparent; border: 2px solid #555; color: white; }
</style>