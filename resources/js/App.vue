<template>
    <div class="min-h-screen flex flex-col">
        <div class="navbar bg-base-200 border-b border-base-300 px-6 min-h-12">
            <RouterLink to="/" class="text-sm font-semibold tracking-tight no-underline text-base-content">
                Model Explorer
            </RouterLink>
        </div>
        <main class="flex-1 p-8 max-w-6xl w-full mx-auto">
            <RouterView />
        </main>
    </div>
</template>
