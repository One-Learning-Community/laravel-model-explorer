<template>
    <div class="min-h-screen flex flex-col">
        <div class="navbar bg-base-200 border-b border-base-300 px-6 min-h-12 gap-6">
            <RouterLink to="/" class="text-sm font-semibold tracking-tight no-underline text-base-content">
                Model Explorer
            </RouterLink>
            <div class="flex gap-4">
                <RouterLink to="/" class="text-sm no-underline text-base-content/60 hover:text-base-content transition-colors" :class="{ 'text-base-content! font-medium': $route.path === '/' }">
                    Models
                </RouterLink>
                <RouterLink to="/graph" class="text-sm no-underline text-base-content/60 hover:text-base-content transition-colors" :class="{ 'text-base-content! font-medium': $route.path === '/graph' }">
                    Graph
                </RouterLink>
            </div>
        </div>
        <main class="flex-1 p-8 max-w-6xl w-full mx-auto">
            <RouterView />
        </main>
    </div>
</template>
