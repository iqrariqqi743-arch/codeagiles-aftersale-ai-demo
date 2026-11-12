<script setup>
import { RouterView } from "vue-router";
</script>

<template>
    <div class="app">
        <RouterView />
    </div>
</template>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 0;
    font-family: Arial, sans-serif;
}

#app {
    min-height: 100vh;
}

.app {
    min-height: 100vh;
}
</style>
