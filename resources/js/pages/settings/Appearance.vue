<script lang="ts" setup>
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <AuthLayout>
        <Head title="Appearance settings" />
        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall description="Update your account's appearance settings" title="Appearance settings" />
                <AppearanceTabs />
            </div>
        </SettingsLayout>
    </AuthLayout>
</template>
