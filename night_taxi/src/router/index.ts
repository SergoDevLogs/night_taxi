import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '@/views/HomeView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home', // Уникальное имя маршрута
      component: HomeView // Компонент, который откроется
    },
    {
      path: '/guide',
      name: 'guide',
      component: () => import('../views/GuideView.vue')
    
    },
    {
      path: '/success',
      name: 'success',
      component: () => import('../views/SuccessView.vue')
    },
    {
      path: '/packing-result',
      name: 'packing-result',
      component: () => import('../views/PackingResultView.vue')
    },
    {
      path: '/packaging',
      name: 'packaging',
      // Ленивая загрузка (как React.lazy) - файл скачается только при переходе
      component: () => import('../views/PackagingView.vue') 
    }
  ]
})

export default router