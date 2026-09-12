<script setup>
import { useRouter } from 'vue-router'
const router = useRouter()

// Функция генерации и скачивания отчета (симуляция бэкенда через Blob)
const downloadReport = () => {
  // 1. Текст, который попадет внутрь скачиваемого файла отчета
  const reportContent = `
ОТЧЕТ ПО УПАКОВКЕ ЗАКАЗА №123
===================================
Статус: Успешно упаковано
Использовано коробок: 1 (Стандартная коробка M)
Общий вес: 1.25 кг
Эффективность заполнения: 75%

Состав заказа:
- Мыло ручной работы (SKU: SOAP_HAND_01) - 1 шт.
- Шампунь Organic (SKU: SHAMPOO_ORG_200) - 1 шт.
- Подарочный набор (SKU: GIFT_BOX_MAX) - 1 шт.

Дата генерации: ${new Date().toLocaleString()}
  `.trim()

  // 2. Создаем Blob (двоичный объект с данными файла) — точь-в-точь как при реальном запросе с responseType: 'blob'
  const blob = new Blob([reportContent], { type: 'text/plain;charset=utf-8' })
  
  // 3. Создаем временную ссылку на этот файл в памяти браузера
  const url = URL.createObjectURL(blob)
  
  // 4. Программно создаем тег <a>, «кликаем» по нему и удаляем
  const link = document.createElement('a')
  link.href = url
  link.download = `packing-report-123.txt` // Можно сменить на .csv или .pdf, когда бэк отдаст реальные
  document.body.appendChild(link)
  link.click()
  
  // 5. Очищаем память
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}

const goHome = () => {
  router.push('/')
}
</script>

<template>
  <div class="success-page">
    <div class="success-card">
      <div class="success-icon">🎉</div>
      <h1 class="title">Заказ успешно упакован!</h1>
      <p class="subtitle">Все товары распределены по коробкам согласно алгоритму.</p>

      <!-- Блок со статистикой -->
      <div class="stats-grid">
        <div class="stat-item">
          <span class="stat-label">Использовано коробок</span>
          <span class="stat-value">1 шт.</span>
        </div>
        <div class="stat-item">
          <span class="stat-label">Общий вес</span>
          <span class="stat-value">1.25 кг</span>
        </div>
        <div class="stat-item">
          <span class="stat-label">Эффективность заполнения</span>
          <span class="stat-value">75%</span>
        </div>
      </div>

      <!-- Кнопки действий -->
      <div class="actions">
        <button class="btn-secondary" @click="downloadReport">
          📥 Скачать отчет (TXT/CSV)
        </button>
        <button class="btn-primary" @click="goHome">
          На главную
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.success-page {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 70vh;
}

.success-card {
  background-color: #2c2c2c;
  border-radius: 24px;
  padding: 48px;
  text-align: center;
  max-width: 550px;
  width: 100%;
  box-shadow: 0 20px 40px rgba(0,0,0,0.4);
}

.success-icon {
  font-size: 64px;
  margin-bottom: 16px;
}

.title {
  color: #FFD700;
  font-size: 28px;
  margin: 0 0 8px 0;
}

.subtitle {
  color: #a0a0a0;
  font-size: 15px;
  margin-bottom: 32px;
}

.stats-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
  margin-bottom: 32px;
  background: #1a1a1a;
  padding: 20px;
  border-radius: 16px;
}

.stat-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid #333;
}
.stat-item:last-child {
  border-bottom: none;
}

.stat-label {
  color: #a0a0a0;
  font-size: 14px;
}

.stat-value {
  color: #FFD700;
  font-weight: bold;
  font-size: 16px;
}

.actions {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.btn-primary, .btn-secondary {
  padding: 14px 24px;
  border-radius: 12px;
  font-weight: bold;
  cursor: pointer;
  border: none;
  font-size: 16px;
  width: 100%;
}
.btn-primary { background: #FFD700; color: #1a1a1a; }
.btn-secondary { background: transparent; border: 2px solid #555; color: white; }
.btn-secondary:hover { border-color: #FFD700; color: #FFD700; }
</style>