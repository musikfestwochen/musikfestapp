<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Users',
        href: '/users',
    },
    {
        title: 'Create',
        href: '/users/create',
    },
];

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('users.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Users" />

        <div class="px-4 py-6">
            <Heading class="mb-4" description="Create a new user" level="2" title="Create User" />

            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <div class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            :tabindex="1"
                            autocomplete="name"
                            autofocus
                            placeholder="Full name"
                            required
                            type="text"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            :tabindex="2"
                            autocomplete="email"
                            placeholder="email@example.com"
                            required
                            type="email"
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <Button :disabled="form.processing" class="mt-2 w-full" tabindex="5" type="submit">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        Create User
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
