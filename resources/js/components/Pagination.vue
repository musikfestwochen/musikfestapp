<script lang="ts" setup>
import Icon from '@/components/Icon.vue';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';

const { items } = defineProps<{
    items: object;
}>();
</script>

<template>
    <div v-if="items.last_page <= 5" class="flex space-x-2">
        <Button v-for="page in items.last_page" :key="page" :variant="items.current_page === page ? 'default' : 'outline'" as-child class="w-10">
            <Link :href="items.path + '?page=' + page" preserve-scroll>
                {{ page }}
            </Link>
        </Button>
    </div>
    <div v-else class="flex items-center space-x-2">
        <Button as-child class="w-10" variant="outline">
            <Link :href="items.path + '?page=1'" preserve-scroll>
                <Icon name="ChevronsLeftIcon"></Icon>
            </Link>
        </Button>
        <Button as-child class="w-10" variant="outline">
            <Link :href="items.prev_page_url" preserve-scroll>
                <Icon name="ChevronLeftIcon"></Icon>
            </Link>
        </Button>
        <div v-for="link in items.links.slice(1, -1)" v-bind:key="link.label">
            <Button v-if="link.url" as-child class="w-10" variant="outline">
                <Link :href="link.url" preserve-scroll> {{ link.label }}</Link>
            </Button>
            <Icon v-else name="EllipsisIcon"></Icon>
        </div>
        <Button as-child class="w-10" variant="outline">
            <Link :href="items.next_page_url" preserve-scroll>
                <Icon name="ChevronRightIcon"></Icon>
            </Link>
        </Button>
        <Button as-child class="w-10" variant="outline">
            <Link :href="items.path + '?page=' + items.last_page" preserve-scroll>
                <Icon name="ChevronsRightIcon"></Icon>
            </Link>
        </Button>
    </div>
</template>
