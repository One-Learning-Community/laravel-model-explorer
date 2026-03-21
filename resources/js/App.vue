<template>
    <div class="shell">
        <header class="header">
            <h1 class="title">Model Explorer</h1>
        </header>
        <main class="content">
            <p class="placeholder">Model Explorer is installed and running.</p>
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
    padding: 1rem 2rem;
    border-bottom: 1px solid #1e293b;
    background: #0f172a;
}

.title {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    color: #f1f5f9;
}

.content {
    flex: 1;
    padding: 2rem;
}

.placeholder {
    color: #64748b;
    font-size: 0.875rem;
}
</style>
