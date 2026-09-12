<script setup>
import { RouterLink, RouterView } from 'vue-router'
import Logo from './components/brand/Logo.vue';
</script>

<template>
  <div class="page-container">
    <!-- ОБЩАЯ ШАПКА ДЛЯ ВСЕХ СТРАНИЦ -->
    <header class="header">
      <div class="logo">
        <span class="logo-icon"><Logo/></span> ФитТара
      </div>
      <nav class="nav-links">
        <RouterLink to="/" exact-active-class="active">Главная</RouterLink>
        <RouterLink to="/packaging" exact-active-class="active">Упаковка</RouterLink>
        <RouterLink to="/guide" exact-active-class="active">Справочник</RouterLink>
      </nav>
    </header>

    <!-- СЮДА ВСТАВЛЯЮТСЯ СТРАНИЦЫ ИЗ ПАПКИ VIEWS -->
    <RouterView />
  </div>
</template>

<style>
/* Глобальный сброс (без scoped!) */
body {
  margin: 0;
  padding: 0;
  background-color: #1a1a1a;
}
</style>

<style>
/* 1. ГЛОБАЛЬНЫЕ СТИЛИ (Фон вокруг приложения) */
body {
  margin: 0;
  padding: 0;
  background-color: #080808; /* Очень темный, почти черный фон браузера */
  /* Центрируем наше приложение по вертикали, если экран очень большой */
  display: flex;
  align-items: center; 
  justify-content: center;
  min-height: 100vh;
}

/* Vue по дефолту монтирует всё в div id="app" */
#app {
  width: 100%;
  padding: 24px; /* Это отступ от краев браузера (чтобы приложение не прилипало) */
  box-sizing: border-box;
}
</style>

<style scoped>
/* 2. СТИЛИ САМОГО ПРИЛОЖЕНИЯ (Наше окно) */
.page-container {
  background-color: #1a1a1a; /* Цвет самого макета (светлее фона) */
  color: white;
  max-width: 1400px; /* Ограничиваем ширину, чтобы на огромных мониторах не разъезжалось */
  min-height: calc(100vh - 48px); /* Высота экрана минус паддинги от #app */
  margin: 0 auto; /* Центрируем по горизонтали */
  padding: 40px 80px;
  font-family: sans-serif;
  
  /* Магия скругления и объема */
  border-radius: 32px; /* Круглые края */
  box-shadow: 0 24px 80px rgba(0, 0, 0, 0.6); /* Мощная тень, чтобы "оторвать" окно от фона */
  box-sizing: border-box;
  
  /* На всякий случай скрываем то, что вылезет за скругленные края */
  overflow: hidden; 
}

/* --- Дальше оставляешь старые стили шапки --- */
.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 80px;
}

.logo {
  font-size: 24px;
  font-weight: bold;
  color: white;
  display: flex;
  align-items: center;
  gap: 8px;
}

.logo-icon {
  color: #FFD700;
}

.nav-links {
  display: flex;
  gap: 32px;
}

.nav-links a {
  color: #a0a0a0;
  text-decoration: none;
  font-size: 16px;
  transition: color 0.2s;
}

.nav-links a:hover {
  color: white;
}

.nav-links a.active {
  color: #FFD700;
  text-decoration: underline;
  text-underline-offset: 8px;
}
</style>