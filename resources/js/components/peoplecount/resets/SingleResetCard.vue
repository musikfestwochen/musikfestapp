<script lang="ts" setup>
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Organization, PeoplecountArea, PeoplecountAreaSingleReset } from '@/types';
import { formatLocalDateTime } from '@/utils/dateTimeHelpers';
import { Link } from '@inertiajs/vue3';
import { Plus, RotateCcw } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    resets: PeoplecountAreaSingleReset[];
    organization: Organization;
    area: PeoplecountArea;
}>();

// Get the most recent reset
const latestReset = computed(() => {
    if (!props.resets || props.resets.length === 0) return null;

    return props.resets.reduce((latest, current) => {
        const latestDate = new Date(latest.effective_at);
        const currentDate = new Date(current.effective_at);
        return currentDate > latestDate ? current : latest;
    }, props.resets[0]);
});

// Format the effective date
const formattedEffectiveDate = computed(() => {
    if (!latestReset.value) return 'No resets yet';
    return formatLocalDateTime(latestReset.value.effective_at);
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <RotateCcw class="h-4 w-4" />
                Manual Resets
            </CardTitle>
        </CardHeader>
        <CardContent>
            <div class="space-y-4">
                <div v-if="latestReset" class="space-y-2">
                    <p class="text-sm font-medium">Latest Reset</p>
                    <div class="grid grid-cols-2 gap-1 text-sm">
                        <span class="text-muted-foreground">Value:</span>
                        <span class="font-medium">{{ latestReset.reset_value }}</span>

                        <span class="text-muted-foreground">Effective At:</span>
                        <span>{{ formattedEffectiveDate }}</span>

                        <span class="text-muted-foreground">Created By:</span>
                        <span>{{ latestReset.created_by?.name || 'Unknown' }}</span>
                    </div>
                </div>
                <div v-else class="text-muted-foreground text-sm">No manual resets have been created for this area yet.</div>

                <Button as-child class="w-full" size="sm" variant="outline">
                    <Link
                        :href="
                            route('peoplecount.areas.single-resets.create', {
                                organization: organization.slug,
                                area: area.id,
                            })
                        "
                    >
                        <Plus class="mr-1 h-4 w-4" />
                        Create Reset
                    </Link>
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
