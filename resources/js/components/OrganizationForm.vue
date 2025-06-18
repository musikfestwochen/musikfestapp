<script lang="ts" setup>
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Organization } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const props = defineProps<{ organization?: Organization }>();

const form = useForm({
    name: props.organization?.name || '',
    slug: props.organization?.slug || '',
    description: props.organization?.description || '',
    email: props.organization?.email || '',
    phone: props.organization?.phone || '',
    website: props.organization?.website || '',
    logo: props.organization?.logo || '',
});

const submit = () => {
    if (props.organization) {
        form.put(route('admin.organizations.update', { id: props.organization.id }));
    } else {
        form.post(route('admin.organizations.store'));
    }
};
</script>
<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="grid max-w-80 gap-6">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" v-model="form.name" :tabindex="1" autofocus placeholder="Organization name" required type="text" />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="slug">Slug</Label>
                <Input id="slug" v-model="form.slug" :tabindex="2" placeholder="organization-slug" required type="text" />
                <InputError :message="form.errors.slug" />
            </div>

            <div class="grid gap-2">
                <Label for="description">Description</Label>
                <Textarea id="description" v-model="form.description" :tabindex="3" placeholder="Organization description" />
                <InputError :message="form.errors.description" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input id="email" v-model="form.email" :tabindex="4" autocomplete="email" placeholder="email@example.com" type="email" />
                <InputError :message="form.errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="phone">Phone</Label>
                <Input id="phone" v-model="form.phone" :tabindex="5" placeholder="+1 (555) 123-4567" type="tel" />
                <InputError :message="form.errors.phone" />
            </div>

            <div class="grid gap-2">
                <Label for="website">Website</Label>
                <Input id="website" v-model="form.website" :tabindex="6" placeholder="https://example.com" type="url" />
                <InputError :message="form.errors.website" />
            </div>

            <div class="grid gap-2">
                <Label for="logo">Logo URL</Label>
                <Input id="logo" v-model="form.logo" :tabindex="7" placeholder="https://example.com/logo.png" type="url" />
                <InputError :message="form.errors.logo" />
            </div>

            <Button :disabled="form.processing" class="mt-2 w-full" tabindex="8" type="submit">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                <span v-else>{{ props.organization ? 'Update Organization' : 'Create Organization' }}</span>
            </Button>
        </div>
    </form>
</template>
