import { createRouter, createWebHistory } from 'vue-router'
import ModelList from './pages/ModelList.vue'
import ModelDetail from './pages/ModelDetail.vue'
import ModelGraph from './pages/ModelGraph.vue'
import ModelRecord from './pages/ModelRecord.vue'

export default createRouter({
    history: createWebHistory(window.modelExplorerBasePath),
    routes: [
        { path: '/', component: ModelList },
        { path: '/models/:model', component: ModelDetail },
        { path: '/models/:model/record', component: ModelRecord },
        { path: '/graph', component: ModelGraph },
    ],
})
