import { createRouter, createWebHistory } from 'vue-router'
import ModelList from './pages/ModelList.vue'
import ModelDetail from './pages/ModelDetail.vue'

export default createRouter({
    history: createWebHistory(window.modelExplorerBasePath),
    routes: [
        { path: '/', component: ModelList },
        { path: '/models/:model', component: ModelDetail },
    ],
})
