<script lang="ts" setup>
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Switch } from '@/components/ui/switch';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const initialValue = computed<boolean>(() => page.props.auth.user.eastereggs_activated as boolean);

const form = useForm({
    eastereggs_activated: initialValue.value,
});

function onToggle(checked: boolean) {
    form.eastereggs_activated = checked;
    form.patch(route('appearance.update'));
}
</script>

<template>
    <AuthLayout>
        <Head title="Appearance settings" />
        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall description="Update your account's appearance settings" title="Appearance settings" />
                <AppearanceTabs />

                <div class="mt-8 max-w-xl rounded-lg border p-4">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-sm font-medium">Easter eggs</h3>
                            <p class="text-xs text-muted-foreground">Toggle fun easter eggs across the app.</p>
                        </div>
                        <Switch :disabled="form.processing" :model-value="form.eastereggs_activated" @update:model-value="onToggle">
                            <template #thumb>
                                <div class="grid h-full w-full place-items-center">
                                    <!-- Spinner when processing -->
                                    <svg
                                        v-if="form.processing"
                                        aria-hidden="true"
                                        class="h-3.5 w-3.5 animate-spin text-muted-foreground"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        xmlns="http://www.w3.org/2000/svg"
                                    >
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" fill="currentColor"></path>
                                    </svg>
                                    <!-- Checkmark when recently successful -->
                                    <svg
                                        v-else-if="form.recentlySuccessful"
                                        aria-hidden="true"
                                        class="h-3.5 w-3.5 text-primary"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        viewBox="0 0 24 24"
                                    >
                                        <path d="M20 6L9 17l-5-5" />
                                    </svg>
                                </div>
                            </template>
                        </Switch>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AuthLayout>
</template>
