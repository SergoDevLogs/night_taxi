<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
const router = useRouter()

const isAssembling = ref(false)
const orderItems = ref([])
const addMode = ref('db')

// База данных
const dbProducts = ref([
  { id: 1, name: 'Мыло ручной работы', sku: 'SOAP_HAND_01', length: 10, width: 5, height: 3, weight: 0.1 },
  { id: 2, name: 'Шампунь Organic', sku: 'SHAMPOO_ORG_200', length: 5, width: 5, height: 15, weight: 0.25 },
  { id: 3, name: 'Подарочный набор', sku: 'GIFT_BOX_MAX', length: 20, width: 20, height: 10, weight: 1.5 },
])

// Состояние для нашего кастомного селекта
const selectedProduct = ref(null)
const isDropdownOpen = ref(false)

const selectProduct = (product) => {
  selectedProduct.value = product
  isDropdownOpen.value = false
}

// Форма ручного ввода
const manualItem = ref({
  name: '',
  sku: '',
  length: null,
  width: null,
  height: null,
  weight: null
})

const startAssembly = () => {
  isAssembling.value = true
}

// Добавление из базы
const addFromDb = () => {
  if (!selectedProduct.value) return
  orderItems.value.push({ ...selectedProduct.value, uniqueId: Date.now() })
  selectedProduct.value = null
}

// Добавление вручную
const addManual = () => {
  if (!manualItem.value.name || !manualItem.value.sku) {
    alert('Заполните хотя бы Название и Артикул!')
    return
  }
  orderItems.value.push({ ...manualItem.value, uniqueId: Date.now() })
  manualItem.value = { name: '', sku: '', length: null, width: null, height: null, weight: null }
}

const removeItem = (uid) => {
  orderItems.value = orderItems.value.filter(item => item.uniqueId !== uid)
}

const proceedToPackaging = () => {
  // Здесь в будущем мы сделаем реальный POST запрос, а пока просто переходим на экран результатов
  router.push('/packing-result')
}
</script>

<template>
  <div class="packaging-page">
    <h1 class="page-title">Сборка заказа</h1>

    <!-- Экран старта -->
    <div v-if="!isAssembling" class="start-screen">
      <button class="btn-primary huge" @click="startAssembly">
        Начать собирать заказ
      </button>
    </div>

    <!-- Интерфейс сборки -->
    <div v-else class="assembly-layout">
      
      <!-- Левая колонка -->
      <div class="panel add-panel">
        <h2 class="panel-title">Добавить товар</h2>
        
        <div class="tabs">
          <button :class="['tab-btn', { active: addMode === 'db' }]" @click="addMode = 'db'">Из базы</button>
          <button :class="['tab-btn', { active: addMode === 'manual' }]" @click="addMode = 'manual'">Вручную</button>
        </div>

        <!-- РЕЖИМ ВЫБОРА ИЗ БАЗЫ (КАСТОМНЫЙ СЕЛЕКТ) -->
        <div v-if="addMode === 'db'" class="tab-content">
          <div class="custom-select-wrapper mb-16">
            <!-- Кнопка-селект, на которую кликают -->
            <div class="dark-input select-trigger" @click="isDropdownOpen = !isDropdownOpen">
              <span :class="{ 'placeholder': !selectedProduct }">
                {{ selectedProduct ? `${selectedProduct.name} (${selectedProduct.sku})` : 'Выберите товар из списка...' }}
              </span>
              <span class="arrow" :class="{ 'open': isDropdownOpen }">▼</span>
            </div>

            <!-- Само выпадающее окошко с товарами -->
            <div v-if="isDropdownOpen" class="custom-dropdown-list">
              <div 
                v-for="p in dbProducts" 
                :key="p.id" 
                class="dropdown-item"
                @click="selectProduct(p)"
              >
                <div class="drop-name">{{ p.name }}</div>
                <div class="drop-sku">{{ p.sku }}</div>
              </div>
            </div>
          </div>

          <button class="btn-secondary w-full" @click="addFromDb" :disabled="!selectedProduct">
            Добавить в заказ
          </button>
        </div>

        <!-- Режим: Вручную -->
        <div v-else class="tab-content">
          <input v-model="manualItem.name" type="text" placeholder="Название (напр. Кружка)" class="dark-input mb-16" />
          <input v-model="manualItem.sku" type="text" placeholder="Артикул (напр. MUG_WHITE)" class="dark-input mb-16" />
          
          <div class="dimensions-grid mb-16">
            <input v-model="manualItem.length" type="number" placeholder="Длина (см)" class="dark-input" />
            <input v-model="manualItem.width" type="number" placeholder="Ширина (см)" class="dark-input" />
            <input v-model="manualItem.height" type="number" placeholder="Высота (см)" class="dark-input" />
            <input v-model="manualItem.weight" type="number" placeholder="Вес (кг)" class="dark-input" />
          </div>
          
          <button class="btn-secondary w-full" @click="addManual">
            Добавить в заказ
          </button>
        </div>
      </div>

      <!-- Правая колонка (Список заказа) -->
      <div class="panel cart-panel">
        <h2 class="panel-title">Текущий заказ ({{ orderItems.length }})</h2>
        
        <div v-if="orderItems.length === 0" class="empty-cart">
          Заказ пока пуст. Добавьте товары.
        </div>
        
        <div v-else class="cart-items">
          <div v-for="item in orderItems" :key="item.uniqueId" class="cart-item">
            <div class="item-info">
              <span class="item-name">{{ item.name }}</span>
              <span class="item-sku">{{ item.sku }}</span>
              <span class="item-specs">
                Размеры: {{ item.length || 0 }}x{{ item.width || 0 }}x{{ item.height || 0 }} см | Вес: {{ item.weight || 0 }} кг
              </span>
            </div>
            <button class="btn-icon" @click="removeItem(item.uniqueId)">✕</button>
          </div>
        </div>

        <div class="cart-footer">
          <button 
            class="btn-primary w-full" 
            :disabled="orderItems.length === 0"
            @click="proceedToPackaging"
          >
            Перейти к упаковке
          </button>
        </div>
      </div>

    </div>
  </div>
</template>

<style scoped>
.packaging-page {
  display: flex;
  flex-direction: column;
  height: 100%;
}

.page-title {
  color: #FFD700;
  font-size: 32px;
  margin-bottom: 40px;
}

.start-screen {
  display: flex;
  justify-content: center;
  align-items: center;
  flex: 1;
  min-height: 400px;
}

.btn-primary.huge {
  font-size: 24px;
  padding: 24px 48px;
  border-radius: 32px;
}

.btn-primary {
  background-color: #FFD700;
  color: #1a1a1a;
  border: none;
  border-radius: 12px;
  padding: 16px;
  font-size: 16px;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-primary:hover:not(:disabled) { background-color: #e6c200; }
.btn-primary:disabled { background-color: #555; color: #888; cursor: not-allowed; }

.btn-secondary {
  background-color: transparent;
  color: #FFD700;
  border: 2px solid #FFD700;
  border-radius: 12px;
  padding: 14px;
  font-size: 16px;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-secondary:hover:not(:disabled) { background-color: rgba(255, 215, 0, 0.1); }
.btn-secondary:disabled { border-color: #555; color: #555; cursor: not-allowed; }

.w-full { width: 100%; }
.mb-16 { margin-bottom: 16px; }

.assembly-layout {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 32px;
  align-items: start;
}

.panel {
  background-color: #2c2c2c;
  border-radius: 16px;
  padding: 32px;
}

.panel-title {
  color: white;
  margin: 0 0 24px 0;
  font-size: 24px;
}

.tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 24px;
  background: #1a1a1a;
  padding: 4px;
  border-radius: 12px;
}
.tab-btn {
  flex: 1;
  background: transparent;
  border: none;
  color: #a0a0a0;
  padding: 12px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: bold;
  transition: all 0.2s;
}
.tab-btn.active {
  background: #2c2c2c;
  color: #FFD700;
}

.dark-input {
  width: 100%;
  background-color: #1a1a1a;
  border: 1px solid #444;
  color: white;
  padding: 16px;
  border-radius: 12px;
  font-size: 16px;
  box-sizing: border-box;
  outline: none;
  transition: border-color 0.2s;
}
.dark-input:focus {
  border-color: #FFD700;
}

/* СТИЛИ ДЛЯ КАСТОМНОГО ВЫПАДАЮЩЕГО ОКНА */
.custom-select-wrapper {
  position: relative;
}

.select-trigger {
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
  user-select: none;
}

.select-trigger .placeholder {
  color: #777;
}

.select-trigger .arrow {
  color: #FFD700;
  font-size: 12px;
  transition: transform 0.2s ease;
}

.select-trigger .arrow.open {
  transform: rotate(180deg);
}

.custom-dropdown-list {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  width: 100%;
  background-color: #1a1a1a;
  border: 1px solid #444;
  border-radius: 12px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.5);
  z-index: 10;
  overflow: hidden;
  max-height: 220px;
  overflow-y: auto;
}

.dropdown-item {
  padding: 14px 16px;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  gap: 2px;
  border-bottom: 1px solid #252525;
  transition: background 0.15s;
}

.dropdown-item:last-child {
  border-bottom: none;
}

.dropdown-item:hover {
  background-color: #333;
}

.drop-name {
  color: white;
  font-weight: 500;
}

.drop-sku {
  color: #FFD700;
  font-size: 12px;
}

.dimensions-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.empty-cart {
  text-align: center;
  color: #888;
  padding: 40px 0;
  font-style: italic;
}

.cart-items {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 24px;
  max-height: 400px;
  overflow-y: auto;
  padding-right: 8px;
}

.cart-items::-webkit-scrollbar {
  width: 6px;
}
.cart-items::-webkit-scrollbar-track {
  background: #1a1a1a;
  border-radius: 4px;
}
.cart-items::-webkit-scrollbar-thumb {
  background: #444;
  border-radius: 4px;
}
.cart-items::-webkit-scrollbar-thumb:hover {
  background: #FFD700;
}

.cart-item {
  background: #1a1a1a;
  border-radius: 12px;
  padding: 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.item-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.item-name { color: white; font-weight: bold; font-size: 16px; }
.item-sku { color: #FFD700; font-size: 14px; }
.item-specs { color: #a0a0a0; font-size: 12px; }

.btn-icon {
  background: transparent;
  border: none;
  color: #a0a0a0;
  cursor: pointer;
  font-size: 20px;
  padding: 8px;
  transition: color 0.2s;
}
.btn-icon:hover { color: #ff4d4d; }

.cart-footer {
  border-top: 1px solid #444;
  padding-top: 24px;
}
</style>