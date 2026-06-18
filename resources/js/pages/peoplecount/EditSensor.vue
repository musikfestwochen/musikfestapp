<script lang="ts" setup>
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import MeasurementCard from '@/components/peoplecount/cards/MeasurementCard.vue';
import SensorForm from '@/components/peoplecount/sensors/SensorForm.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import { BreadcrumbItem, Organization, PeoplecountSensor, PeoplecountSensorShare } from '@/types';
import { formatLocalDateTime, getUTCStringFromLocal } from '@/utils/dateTimeHelpers';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    sensor: PeoplecountSensor;
    organization: Organization;
    organizations: Organization[];
}>();

const shareForm = useForm({
    borrower_organization_id: '',
    starts_at: '',
    ends_at: '',
});

const editingShareId = ref<number | null>(null);

const editShareForm = useForm({
    borrower_organization_id: '',
    starts_at: '',
    ends_at: '',
});

const formatDateTimeLocal = (value: string) => {
    const date = new Date(value);
    const localDate = new Date(date.getTime() - date.getTimezoneOffset() * 60000);

    return localDate.toISOString().slice(0, 16);
};

const submitShare = () => {
    shareForm
        .transform((data) => ({
            ...data,
            starts_at: getUTCStringFromLocal(new Date(data.starts_at)),
            ends_at: getUTCStringFromLocal(new Date(data.ends_at)),
        }))
        .post(
            route('peoplecount.sensors.shares.store', {
                organization: props.organization.slug,
                sensor: props.sensor.id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => shareForm.reset(),
            },
        );
};

const editShare = (share: PeoplecountSensorShare) => {
    editingShareId.value = share.id;
    editShareForm.clearErrors();
    editShareForm.borrower_organization_id = share.borrower_organization_id.toString();
    editShareForm.starts_at = formatDateTimeLocal(share.starts_at);
    editShareForm.ends_at = formatDateTimeLocal(share.ends_at);
};

const cancelEditShare = () => {
    editingShareId.value = null;
    editShareForm.reset();
    editShareForm.clearErrors();
};

const updateShare = (share: PeoplecountSensorShare) => {
    editShareForm
        .transform((data) => ({
            ...data,
            starts_at: getUTCStringFromLocal(new Date(data.starts_at)),
            ends_at: getUTCStringFromLocal(new Date(data.ends_at)),
        }))
        .put(
            route('peoplecount.sensors.shares.update', {
                organization: props.organization.slug,
                sensor: props.sensor.id,
                share: share.id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => cancelEditShare(),
            },
        );
};

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: props.organization.name,
        href: route('organization.dashboard', { organization: props.organization.slug }),
    },
    {
        title: 'Sensors',
        href: route('peoplecount.sensors.index', { organization: props.organization.slug }),
    },
    {
        title: 'Edit',
        href: route('peoplecount.sensors.edit', {
            organization: props.organization.slug,
            sensor: props.sensor.id,
        }),
    },
];
</script>

<template>
    <Layout :breadcrumbs="breadcrumbItems">
        <Head title="Sensors" />

        <div class="px-4 py-6">
            <Heading title="Edit Sensor" />
            <SensorForm :organization="props.organization" :sensor="props.sensor" />

            <div class="mt-8 rounded-lg border p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <Heading description="Hide retired sensors without breaking assignments or history" title="Archive" />
                        <p v-if="props.sensor.archived_at" class="text-muted-foreground mt-2 text-sm">
                            Archived {{ formatLocalDateTime(props.sensor.archived_at) }}
                        </p>
                    </div>

                    <Link
                        v-if="props.sensor.archived_at"
                        :href="route('peoplecount.sensors.archive.destroy', { organization: props.organization.slug, sensor: props.sensor.id })"
                        as="button"
                        method="delete"
                    >
                        <Button variant="outline">Unarchive</Button>
                    </Link>
                    <Link
                        v-else
                        :href="route('peoplecount.sensors.archive.store', { organization: props.organization.slug, sensor: props.sensor.id })"
                        as="button"
                        method="post"
                    >
                        <Button variant="outline">Archive</Button>
                    </Link>
                </div>
            </div>

            <div class="mt-8 rounded-lg border p-6">
                <Heading description="Allow another organization to assign this sensor inside a selected period" title="Sharing" />

                <form class="mt-4 grid max-w-3xl gap-4 md:grid-cols-4" @submit.prevent="submitShare">
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="borrower_organization_id">Organization</Label>
                        <select
                            id="borrower_organization_id"
                            v-model="shareForm.borrower_organization_id"
                            class="bg-background rounded-md border px-3 py-2"
                            required
                        >
                            <option value="" disabled>Select organization</option>
                            <option v-for="org in props.organizations" :key="org.id" :value="org.id">
                                {{ org.name }}
                            </option>
                        </select>
                        <InputError :message="shareForm.errors.borrower_organization_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="starts_at">Starts at</Label>
                        <Input id="starts_at" v-model="shareForm.starts_at" required type="datetime-local" />
                        <InputError :message="shareForm.errors.starts_at" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="ends_at">Ends at</Label>
                        <Input id="ends_at" v-model="shareForm.ends_at" required type="datetime-local" />
                        <InputError :message="shareForm.errors.ends_at" />
                    </div>

                    <Button :disabled="shareForm.processing" class="md:col-span-4" type="submit">
                        <LoaderCircle v-if="shareForm.processing" class="h-4 w-4 animate-spin" />
                        <span v-else>Share Sensor</span>
                    </Button>
                </form>

                <div v-if="(props.sensor.shares?.length ?? 0) > 0" class="mt-6 divide-y rounded-md border">
                    <div v-for="share in props.sensor.shares" :key="share.id" class="p-4">
                        <form v-if="editingShareId === share.id" class="grid gap-4 md:grid-cols-4" @submit.prevent="updateShare(share)">
                            <div class="grid gap-2 md:col-span-2">
                                <Label :for="`edit_borrower_organization_id_${share.id}`">Organization</Label>
                                <select
                                    :id="`edit_borrower_organization_id_${share.id}`"
                                    v-model="editShareForm.borrower_organization_id"
                                    :disabled="(share.assignments_count ?? 0) > 0"
                                    class="bg-background rounded-md border px-3 py-2 disabled:opacity-50"
                                    required
                                >
                                    <option v-for="org in props.organizations" :key="org.id" :value="org.id.toString()">
                                        {{ org.name }}
                                    </option>
                                </select>
                                <InputError :message="editShareForm.errors.borrower_organization_id" />
                                <p v-if="(share.assignments_count ?? 0) > 0" class="text-muted-foreground text-xs">
                                    Borrowing organization cannot change while assignments use this share.
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label :for="`edit_starts_at_${share.id}`">Starts at</Label>
                                <Input :id="`edit_starts_at_${share.id}`" v-model="editShareForm.starts_at" required type="datetime-local" />
                                <InputError :message="editShareForm.errors.starts_at" />
                            </div>

                            <div class="grid gap-2">
                                <Label :for="`edit_ends_at_${share.id}`">Ends at</Label>
                                <Input :id="`edit_ends_at_${share.id}`" v-model="editShareForm.ends_at" required type="datetime-local" />
                                <InputError :message="editShareForm.errors.ends_at" />
                            </div>

                            <div class="flex gap-2 md:col-span-4">
                                <Button :disabled="editShareForm.processing" size="sm" type="submit">
                                    <LoaderCircle v-if="editShareForm.processing" class="h-4 w-4 animate-spin" />
                                    <span v-else>Save</span>
                                </Button>
                                <Button :disabled="editShareForm.processing" size="sm" type="button" variant="outline" @click="cancelEditShare"
                                    >Cancel</Button
                                >
                            </div>
                        </form>

                        <div v-else class="flex items-center justify-between gap-4">
                            <div class="text-sm">
                                <p class="font-medium">{{ share.borrower_organization?.name ?? 'Unknown organization' }}</p>
                                <p class="text-muted-foreground">
                                    {{ formatLocalDateTime(share.starts_at) }} to {{ formatLocalDateTime(share.ends_at) }} ·
                                    {{ share.assignments_count ?? 0 }} assignments
                                </p>
                            </div>

                            <div class="flex gap-2">
                                <Button size="sm" type="button" variant="outline" @click="editShare(share)">Edit</Button>

                                <Button
                                    v-if="(share.assignments_count ?? 0) > 0"
                                    disabled
                                    size="sm"
                                    title="Shares with assignments cannot be deleted"
                                    variant="destructive"
                                >
                                    Delete
                                </Button>
                                <Link
                                    v-else
                                    :href="
                                        route('peoplecount.sensors.shares.destroy', {
                                            organization: props.organization.slug,
                                            sensor: props.sensor.id,
                                            share: share.id,
                                        })
                                    "
                                    as="button"
                                    method="delete"
                                    preserve-scroll
                                >
                                    <Button size="sm" variant="destructive">Delete</Button>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <p v-else class="text-muted-foreground mt-4 text-sm">This sensor is not shared.</p>
            </div>

            <div class="mt-8">
                <Heading title="Last Measurements" />

                <div v-if="(props.sensor.interval_counts?.length ?? 0) > 0" class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <MeasurementCard v-for="measurement in props.sensor.interval_counts" :key="measurement.id" :measurement="measurement" />
                </div>

                <div v-else class="text-muted-foreground mt-4 text-sm">No measurements available.</div>
            </div>
        </div>
    </Layout>
</template>
