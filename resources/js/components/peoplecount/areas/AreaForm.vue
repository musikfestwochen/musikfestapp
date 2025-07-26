<script lang="ts" setup>
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Organization, PeoplecountArea, PeoplecountEvent } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const props = defineProps<{
    area?: PeoplecountArea;
    organization: Organization;
    events: PeoplecountEvent[];
}>();

const form = useForm({
    name: props.area?.name || '',
    event_id: props.area?.event_id || '',
});

const submit = () => {
    if (props.area && props.organization) {
        form.put(
            route('peoplecount.areas.update', {
                area: props.area.id,
                organization: props.organization.slug,
            }),
        );
    } else if (props.organization) {
        form.post(route('peoplecount.areas.store', { organization: props.organization.slug }));
    }
};
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="grid max-w-xl gap-6">
            <div class="grid gap-2">
                <Label for="name">Area Name</Label>
                <Input id="name" v-model="form.name" :tabindex="1" autocomplete="on" autofocus placeholder="Area Name" required type="text" />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="event_id">Event</Label>
                <Select v-model="form.event_id" required>
                    <SelectTrigger id="event_id" :tabindex="2">
                        <SelectValue placeholder="Select an event" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="event in props.events" :key="event.id" :value="event.id.toString()">
                            {{ event.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.event_id" />
                <p class="text-sm text-muted-foreground">Select the event this area belongs to.</p>
            </div>

            <Button :disabled="form.processing || !form.name || !form.event_id" class="mt-2 w-full" tabindex="3" type="submit">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                <span v-else>{{ props.area ? 'Update Area' : 'Create Area' }}</span>
            </Button>
        </div>
    </form>
</template>
