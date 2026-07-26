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
    <div>
        <Heading description="Manage your profile and account settings" title="Settings" />

        <div class="flex flex-col space-y-8">
            <aside class="w-full">
                <nav class="flex flex-col space-y-1 space-x-0">
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="item.route"
                        :class="['w-full justify-start', { 'bg-muted': currentRoute === item.route }]"
                        as-child
                        variant="ghost"
                    >
                        <Link :href="route(item.route!)">
                            {{ item.title }}
                        </Link>
                    </Button>
                    <Separator class="my-2" />
                    <Button as-child class="w-full justify-start" variant="ghost">
                        <Link :href="route('organization-selection.index')">Back to Home</Link>
                    </Button>
                    <Button as-child class="w-full justify-start" variant="ghost">
                        <Link :href="route('logout')" as="button" method="post">Logout</Link>
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
