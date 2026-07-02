<script lang="ts" setup>
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Organization } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    organizations: Organization[];
}>();

// Use props in computed property to avoid ESLint warning
const organizationsCount = computed(() => props.organizations.length);

const form = useForm({
    organization_id: -1,
});

const submit = (organizationId: number) => {
    form.organization_id = organizationId;
    form.post(route('organization-selection.store'));
};
</script>

<template>
    <Head title="Musikfestapp" />

    <AuthLayout :class="{ 'cursor-wait': form.processing }" description="Please select an organization to continue" title="Select Organization">
        <InputError :message="form.errors.organization_id" class="mt-2" />

        <div class="flex flex-col gap-4">
            <div v-if="organizationsCount === 0" class="text-center">
                <p class="text-lg">You don't have access to any organizations.</p>
                <p class="mt-2">Please contact an organization administrator do add you.</p>
            </div>

            <div v-else class="flex flex-col gap-4">
                <Card
                    v-for="organization in organizations"
                    :key="organization.id"
                    :class="{ 'opacity-50': form.processing, 'cursor-pointer': !form.processing }"
                    class="transition-all hover:shadow-md"
                    @click="!form.processing && submit(organization.id)"
                >
                    <CardHeader>
                        <CardTitle>{{ organization.name }}</CardTitle>
                        <CardDescription>Click to select {{ organization.name }}</CardDescription>
                    </CardHeader>
                </Card>
            </div>

            <div class="mt-8 text-center">
                <TextLink :href="route('logout')" as="button" class="mx-auto block cursor-pointer text-sm" method="post">Log out</TextLink>
            </div>
        </div>
    </AuthLayout>
</template>
