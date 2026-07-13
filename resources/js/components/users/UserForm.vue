<script lang="ts" setup>
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Organization, User } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const props = defineProps<{ user?: User; organization?: Organization; organizations?: Organization[] }>();

const form = useForm({
    name: props.user?.name || '',
    email: props.user?.email || '',
    phone: props.user?.phone || '',
    organization_ids: props.user?.organizations?.map((organization) => organization.id) || [],
});

const toggleOrganization = (organizationId: number, checked: boolean) => {
    form.organization_ids = checked
        ? [...form.organization_ids, organizationId]
        : form.organization_ids.filter((selectedOrganizationId) => selectedOrganizationId !== organizationId);
};

const submit = () => {
    if (props.user && props.organization) {
        form.put(route('orgmgmt.users.update', { user: props.user.id, organization: props.organization.slug }));
    } else if (props.user) {
        form.put(route('admin.users.update', { id: props.user.id }));
    } else if (props.organization) {
        form.post(route('orgmgmt.users.store', { organization: props.organization.slug }));
    } else if (!props.user && !props.organization) {
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

            <div class="grid gap-2">
                <Label for="phone">Phone number</Label>
                <Input id="phone" v-model="form.phone" :tabindex="3" autocomplete="tel" placeholder="+41 79 123 45 67" type="tel" />
                <InputError :message="form.errors.phone" />
            </div>

            <div v-if="props.user && props.organizations" class="grid gap-3">
                <Label>Organizations</Label>
                <div class="grid gap-2 rounded-md border p-3">
                    <label
                        v-for="availableOrganization in props.organizations"
                        :key="availableOrganization.id"
                        class="flex items-center gap-2 text-sm"
                    >
                        <Checkbox
                            :checked="form.organization_ids.includes(availableOrganization.id)"
                            @update:checked="(checked) => toggleOrganization(availableOrganization.id, checked === true)"
                        />
                        <span>{{ availableOrganization.name }}</span>
                    </label>
                </div>
                <InputError :message="form.errors.organization_ids" />
            </div>

            <Button :disabled="form.processing" class="mt-2 w-full" tabindex="5" type="submit">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                <span v-else>{{ props.user ? 'Update User' : 'Create User' }}</span>
            </Button>
        </div>
    </form>
</template>
