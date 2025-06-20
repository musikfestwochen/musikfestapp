<script lang="ts" setup>
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { User } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const props = defineProps<{ user?: User }>();

const form = useForm({
    name: props.user?.name || '',
    email: props.user?.email || '',
});

const submit = () => {
    if (props.user) {
        form.put(route('admin.users.update', { id: props.user.id }));
    } else {
        form.post(route('admin.users.store'));
    }
};
</script>
<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="grid max-w-80 gap-6">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" v-model="form.name" :tabindex="1" autocomplete="name" autofocus placeholder="Full name" required type="text" />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input id="email" v-model="form.email" :tabindex="2" autocomplete="email" placeholder="email@example.com" required type="email" />
                <InputError :message="form.errors.email" />
            </div>

            <Button :disabled="form.processing" class="mt-2 w-full" tabindex="5" type="submit">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                <span v-else>{{ props.user ? 'Update User' : 'Create User' }}</span>
            </Button>
        </div>
    </form>
</template>
