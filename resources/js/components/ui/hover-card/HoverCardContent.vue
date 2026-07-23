<script setup lang="ts">
import { cn } from '@/lib/utils';
import { HoverCardContent, HoverCardPortal, useForwardProps, type HoverCardContentProps } from 'radix-vue';
import { computed, type HTMLAttributes } from 'vue';

defineOptions({ inheritAttrs: false });

const props = withDefaults(defineProps<HoverCardContentProps & { class?: HTMLAttributes['class'] }>(), {
    sideOffset: 4,
});
const delegatedProps = computed(() => {
    const { class: _, ...delegated } = props;

    return delegated;
});
const forwarded = useForwardProps(delegatedProps);
</script>

<template>
    <HoverCardPortal>
        <HoverCardContent
            v-bind="{ ...forwarded, ...$attrs }"
            :class="
                cn(
                    'z-50 w-64 rounded-md border bg-popover p-4 text-popover-foreground shadow-md outline-none data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95',
                    props.class,
                )
            "
        >
            <slot />
        </HoverCardContent>
    </HoverCardPortal>
</template>
