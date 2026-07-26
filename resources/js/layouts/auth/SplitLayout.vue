<script lang="ts" setup>
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
// Responsive and LQIP background images (static imports for Vite compatibility)
import bg1280Jpg from '../../../img/mfw_background_01-1280.jpg';
import bg1280Webp from '../../../img/mfw_background_01-1280.webp';
import bg1920Jpg from '../../../img/mfw_background_01-1920.jpg';
import bg1920Webp from '../../../img/mfw_background_01-1920.webp';
import bg480Jpg from '../../../img/mfw_background_01-480.jpg';
import bg480Webp from '../../../img/mfw_background_01-480.webp';
import bg768Jpg from '../../../img/mfw_background_01-768.jpg';
import bg768Webp from '../../../img/mfw_background_01-768.webp';
import lqipJpg from '../../../img/mfw_background_01-lqip.jpg';
import lqipWebp from '../../../img/mfw_background_01-lqip.webp';

import { onMounted, ref } from 'vue';

const loaded = ref(false);
const bgUrl = ref(lqipJpg); // Start with LQIP JPEG as fallback

const bgImages = [
    { size: 480, jpg: bg480Jpg, webp: bg480Webp },
    { size: 768, jpg: bg768Jpg, webp: bg768Webp },
    { size: 1280, jpg: bg1280Jpg, webp: bg1280Webp },
    { size: 1920, jpg: bg1920Jpg, webp: bg1920Webp },
];

// Check WebP support
const supportsWebP = ref(false);

function checkWebPSupport() {
    return new Promise<boolean>((resolve) => {
        const webP = new Image();
        webP.onload = webP.onerror = function () {
            resolve(webP.height === 2);
        };
        webP.src = 'data:image/webp;base64,UklGRiIAAABXRUJQVlA4TAYAAAAvAAAAAAfQ//73v/+BiOh/AAA=';
    });
}

function selectBg() {
    const w = window.innerWidth;
    let img = bgImages[0];
    if (w >= 1920) img = bgImages[3];
    else if (w >= 1280) img = bgImages[2];
    else if (w >= 768) img = bgImages[1];

    return supportsWebP.value ? img.webp : img.jpg;
}

onMounted(async () => {
    // Check WebP support first
    supportsWebP.value = await checkWebPSupport();

    // Set initial LQIP
    bgUrl.value = supportsWebP.value ? lqipWebp : lqipJpg;

    // Preload full-res image and fade in
    const selectedBg = selectBg();
    const img = new window.Image();
    img.src = selectedBg;
    img.onload = () => {
        bgUrl.value = selectedBg;
        loaded.value = true;
    };
});

const page = usePage<SharedData>();
const name = page.props.name;
const quote = page.props.quote;

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="relative grid h-dvh items-center justify-center overflow-hidden px-8 sm:px-0 lg:max-w-none lg:grid-cols-3 lg:px-0">
        <div class="bg-muted relative col-span-2 hidden h-full flex-col p-10 text-white lg:flex dark:border-r">
            <div
                :style="{ backgroundImage: `url(${bgUrl})`, filter: loaded ? 'none' : 'blur(16px)', transition: 'filter 0.6s' }"
                class="absolute inset-0 bg-cover bg-center"
            />
            <Link :href="route('home')" class="relative z-20 flex items-center text-lg font-medium">
                <AppLogoIcon class="mr-2 size-8 fill-current text-white" />
                {{ name }}
            </Link>
            <div v-if="quote" class="relative z-20 mt-auto">
                <blockquote class="space-y-2">
                    <p class="text-lg">&ldquo;{{ quote.message }}&rdquo;</p>
                    <footer class="text-sm text-neutral-300">{{ quote.author }}</footer>
                </blockquote>
            </div>
        </div>
        <div class="h-full overflow-y-auto lg:p-8">
            <div class="flex min-h-full w-full">
                <div class="mx-auto flex w-full max-w-xl flex-col space-y-6">
                    <div class="flex flex-col space-y-2 text-center">
                        <h1 v-if="title" class="text-xl font-medium tracking-tight">{{ title }}</h1>
                        <p v-if="description" class="text-muted-foreground text-sm">{{ description }}</p>
                    </div>
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
