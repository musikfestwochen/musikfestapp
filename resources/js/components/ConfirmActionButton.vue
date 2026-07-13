<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { router } from '@inertiajs/vue3';
import type { Component } from 'vue';

const props = withDefaults(
    defineProps<{
        href: string;
        method?: 'delete' | 'patch' | 'post';
        label: string;
        title: string;
        description: string;
        confirmLabel: string;
        icon?: Component;
        variant?: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
        only?: string[];
        preserveScroll?: boolean;
        preserveState?: boolean;
    }>(),
    {
        method: 'delete',
        icon: undefined,
        only: undefined,
        variant: 'destructive',
        preserveScroll: true,
        preserveState: false,
    },
);

function confirm(): void {
    router.visit(props.href, {
        method: props.method,
        only: props.only,
        preserveScroll: props.preserveScroll,
        preserveState: props.preserveState,
    });
}
</script>

<template>
    <AlertDialog>
        <AlertDialogTrigger as-child>
            <Button :variant="variant" size="sm" @click.stop>
                <component :is="icon" v-if="icon" class="mr-1 h-4 w-4" />
                {{ label }}
            </Button>
        </AlertDialogTrigger>
        <AlertDialogContent @click.stop>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ title }}</AlertDialogTitle>
                <AlertDialogDescription>{{ description }}</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction class="bg-destructive text-destructive-foreground hover:bg-destructive/90" @click="confirm">
                    {{ confirmLabel }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
