<script lang="ts" setup>
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { Organization, PeoplecountArea, PeoplecountAreaRecurringReset } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { recurringResetColumns } from './columns';

const props = defineProps<{
    resets: PeoplecountAreaRecurringReset[];
    organization: Organization;
    area: PeoplecountArea;
}>();

const columns = recurringResetColumns(props.organization, props.area);
const { can } = usePermissions();
</script>

<template>
    <DataTable :columns="columns" :data="resets" filter-column="notes" search-placeholder="Search recurring resets..." title="Recurring Resets">
        <template #actions>
            <Button v-if="can('peoplecount.area_resets.create')" as-child size="sm" variant="default">
                <Link
                    :href="
                        route('peoplecount.areas.recurring-resets.create', {
                            organization: props.organization.slug,
                            area: props.area.id,
                        })
                    "
                >
                    <Plus class="mr-1 h-4 w-4" />
                    Create Recurring Reset
                </Link>
            </Button>
        </template>
    </DataTable>
</template>
