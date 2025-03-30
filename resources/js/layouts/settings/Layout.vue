<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        route: 'profile.edit',
    },
    {
        title: 'Password',
        route: 'password.edit',
    },
    {
        title: 'Appearance',
        route: 'appearance',
    },
];

const currentRoute = route().current();
</script>

<template>
    <div class="px-4 py-6">
        <Heading description="Manage your profile and account settings" title="Settings" />

        <div class="flex flex-col space-y-8 md:space-y-0 lg:flex-row lg:space-x-12 lg:space-y-0">
            <aside class="w-full max-w-xl lg:w-48">
                <nav class="flex flex-col space-x-0 space-y-1">
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="item.route"
                        :class="['w-full justify-start', { 'bg-muted': currentRoute === item.route }]"
                        as-child
                        variant="ghost"
                    >
                        <Link :href="route(item.route)">
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 md:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
