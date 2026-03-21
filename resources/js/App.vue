<template>
    <div class="shell">
        <header class="header">
            <RouterLink to="/" class="brand">Model Explorer</RouterLink>
        </header>
        <main class="content">
            <RouterView />
        </main>
    </div>
</template>

<style scoped>
.shell {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.header {
    padding: 0.875rem 2rem;
    border-bottom: 1px solid #1e293b;
    background: #0f172a;
    display: flex;
    align-items: center;
}

.brand {
    font-size: 0.9375rem;
    font-weight: 600;
    color: #f1f5f9;
    text-decoration: none;
    letter-spacing: -0.01em;
}

.brand:hover {
    color: #60a5fa;
}

.content {
    flex: 1;
    padding: 2rem;
    max-width: 1100px;
    width: 100%;
    margin: 0 auto;
}
</style>
