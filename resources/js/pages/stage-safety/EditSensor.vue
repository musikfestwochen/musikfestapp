<script setup lang="ts">
import ConfirmActionButton from '@/components/ConfirmActionButton.vue';
import Heading from '@/components/Heading.vue';
import SensorForm from '@/components/stage-safety/sensors/SensorForm.vue';
import SensorTokenDialog from '@/components/stage-safety/sensors/SensorTokenDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { usePermissions } from '@/composables/usePermissions';
import Layout from '@/layouts/orgmgmt/Layout.vue';
import type { BreadcrumbItem, Organization, StageSafetySensor, StageSafetySensorFormData, StageSafetySensorType } from '@/types';
import { formatLocalDateTime } from '@/utils/dateTimeHelpers';
import { Head, Link, router, useForm, useHttp } from '@inertiajs/vue3';
import { Archive, KeyRound, RotateCcw, Trash2, Undo2 } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    organization: Organization;
    sensor: StageSafetySensor;
    sensorTypes: StageSafetySensorType[];
    status?: string;
}>();

const form = useForm<StageSafetySensorFormData>({
    manufacturer: props.sensor.manufacturer,
    model: props.sensor.model,
    identifier: props.sensor.identifier,
    name: props.sensor.name,
    location: props.sensor.location,
    stale_after_seconds: props.sensor.stale_after_seconds,
});
const tokenRequest = useHttp<Record<string, never>, { token: string }>({});
const token = ref<string | null>(null);
const tokenRegenerationPending = ref(false);
const confirmDialog = useConfirmDialog();
const { can } = usePermissions();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: props.organization.name,
        href: route('organization.dashboard', { organization: props.organization.slug }),
    },
    {
        title: 'Stage Safety Sensors',
        href: route('stage-safety.sensors.index', { organization: props.organization.slug }),
    },
    {
        title: props.sensor.name || props.sensor.identifier,
        href: route('stage-safety.sensors.edit', {
            organization: props.organization.slug,
            stageSafetySensor: props.sensor.id,
        }),
    },
];

function submit(): void {
    form.put(
        route('stage-safety.sensors.update', {
            organization: props.organization.slug,
            stageSafetySensor: props.sensor.id,
        }),
    );
}

function updateForm(values: Partial<StageSafetySensorFormData>): void {
    Object.assign(form, values);
}

async function regenerateToken(): Promise<void> {
    if (tokenRegenerationPending.value) return;

    tokenRegenerationPending.value = true;

    try {
        const confirmed = await confirmDialog.confirm({
            title: props.sensor.has_active_token ? 'Replace sensor token?' : 'Generate sensor token?',
            description: props.sensor.has_active_token
                ? 'The current API token will stop working immediately.'
                : 'A new API token will be shown once.',
            confirmText: props.sensor.has_active_token ? 'Replace token' : 'Generate token',
        });

        if (!confirmed) return;

        const response = await tokenRequest.post(
            route('stage-safety.sensors.regenerate-token', {
                organization: props.organization.slug,
                stageSafetySensor: props.sensor.id,
            }),
        );
        token.value = response.token;
    } catch {
        // useHttp retains request errors and processing state.
    } finally {
        tokenRegenerationPending.value = false;
    }
}

function acknowledgeToken(): void {
    token.value = null;
    router.reload({ only: ['sensor'] });
}
</script>

<template>
    <Layout :breadcrumbs="breadcrumbs">
        <Head title="Edit Stage Safety Sensor" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600 dark:text-green-400">
            {{ status }}
        </div>

        <div class="space-y-8 px-4 py-6">
            <section>
                <Heading description="Update identification, installation, and freshness settings" title="Edit Stage Safety Sensor" />
                <div class="mt-6">
                    <SensorForm
                        :errors="form.errors"
                        :form="form"
                        :processing="form.processing"
                        :sensor-types="sensorTypes"
                        submit-label="Update Sensor"
                        @change="updateForm"
                        @submit="submit"
                    />
                </div>
            </section>

            <section class="rounded-lg border p-6">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div class="space-y-2">
                        <Heading description="Token used to authenticate Stage Safety API requests" title="Sensor API Token" />
                        <Badge :variant="sensor.has_active_token ? 'default' : 'secondary'">
                            {{ sensor.has_active_token ? 'Active' : 'Not active' }}
                        </Badge>
                    </div>

                    <div v-if="can('stage-safety.sensors.update')" class="flex flex-wrap gap-2">
                        <Button :disabled="tokenRegenerationPending" size="sm" type="button" @click="regenerateToken">
                            <RotateCcw v-if="sensor.has_active_token" class="mr-1 size-4" />
                            <KeyRound v-else class="mr-1 size-4" />
                            {{ sensor.has_active_token ? 'Replace Token' : 'Generate Token' }}
                        </Button>
                        <ConfirmActionButton
                            v-if="sensor.has_active_token"
                            :href="
                                route('stage-safety.sensors.revoke-token', {
                                    organization: organization.slug,
                                    stageSafetySensor: sensor.id,
                                })
                            "
                            :icon="Trash2"
                            confirm-label="Revoke token"
                            description="API requests using this token will fail authentication until a new token is generated."
                            label="Revoke"
                            title="Revoke sensor token?"
                        />
                    </div>
                </div>
            </section>

            <section class="rounded-lg border p-6">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <Heading description="Hide retired sensors and revoke their API tokens" title="Archive" />
                        <p v-if="sensor.archived_at" class="text-muted-foreground mt-2 text-sm">
                            Archived {{ formatLocalDateTime(sensor.archived_at) }}
                        </p>
                    </div>

                    <Link
                        v-if="sensor.archived_at"
                        :href="
                            route('stage-safety.sensors.archive.destroy', {
                                organization: organization.slug,
                                stageSafetySensor: sensor.id,
                            })
                        "
                        as="button"
                        method="delete"
                    >
                        <Button variant="outline"><Undo2 class="mr-1 size-4" />Restore</Button>
                    </Link>
                    <ConfirmActionButton
                        v-else
                        :href="
                            route('stage-safety.sensors.archive.store', {
                                organization: organization.slug,
                                stageSafetySensor: sensor.id,
                            })
                        "
                        :icon="Archive"
                        confirm-label="Archive sensor"
                        description="Archiving immediately revokes every API token for this sensor."
                        label="Archive"
                        method="post"
                        title="Archive this sensor?"
                        variant="outline"
                    />
                </div>
            </section>
        </div>

        <SensorTokenDialog :open="token !== null" :token="token || ''" @acknowledged="acknowledgeToken" />
    </Layout>
</template>
