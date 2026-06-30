<script lang="ts" setup>
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Organization, PeoplecountEvent } from '@/types';
import { datetimeLocalToUTCString, utcStringToDatetimeLocal } from '@/utils/dateTimeHelpers';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const props = defineProps<{ event?: PeoplecountEvent; organization: Organization }>();

const form = useForm({
    name: props.event?.name || '',
    starts_at: utcStringToDatetimeLocal(props.event?.starts_at),
    ends_at: utcStringToDatetimeLocal(props.event?.ends_at),
});

const hasValidDateRange = () => Boolean(form.starts_at && form.ends_at && form.starts_at < form.ends_at);

const submitForm = () =>
    form.transform((data) => ({
        ...data,
        starts_at: datetimeLocalToUTCString(data.starts_at),
        ends_at: datetimeLocalToUTCString(data.ends_at),
    }));

const submit = () => {
    if (props.event && props.organization) {
        submitForm().put(
            route('peoplecount.events.update', {
                event: props.event.id,
                organization: props.organization.slug,
            }),
        );
    } else if (props.organization) {
        submitForm().post(route('peoplecount.events.store', { organization: props.organization.slug }));
    }
};
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="grid max-w-xl gap-6">
            <div class="grid gap-2">
                <Label for="name">Event Name</Label>
                <Input id="name" v-model="form.name" :tabindex="1" autofocus placeholder="Event Name" required type="text" />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="starts_at">Starts at</Label>
                    <Input id="starts_at" v-model="form.starts_at" :tabindex="2" required type="datetime-local" />
                    <InputError :message="form.errors.starts_at" />
                </div>

                <div class="grid gap-2">
                    <Label for="ends_at">Ends at</Label>
                    <Input id="ends_at" v-model="form.ends_at" :tabindex="3" required type="datetime-local" />
                    <InputError :message="form.errors.ends_at" />
                </div>
                <p class="text-muted-foreground text-sm sm:col-span-2">
                    Select the start and end date/time for your event. Times are in your local timezone.
                </p>
                <p v-if="form.starts_at && form.ends_at && form.starts_at >= form.ends_at" class="text-destructive text-sm sm:col-span-2">
                    End must be after start.
                </p>
            </div>

            <Button :disabled="form.processing || !hasValidDateRange()" class="mt-2 w-full" tabindex="4" type="submit">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                <span v-else>{{ props.event ? 'Update Event' : 'Create Event' }}</span>
            </Button>
        </div>
    </form>
</template>
