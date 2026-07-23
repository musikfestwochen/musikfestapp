<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useToast } from '@/components/ui/toast/use-toast';
import { Copy, KeyRound } from 'lucide-vue-next';

const props = defineProps<{
    open: boolean;
    token: string;
}>();

const emit = defineEmits<{
    acknowledged: [];
}>();

const { toast } = useToast();

async function copyToken(): Promise<void> {
    try {
        await navigator.clipboard.writeText(props.token);
        toast({ title: 'Token copied', description: 'Store it in the API client configuration now.' });
    } catch {
        toast({ title: 'Copy failed', description: 'Select and copy the token manually.', variant: 'destructive' });
    }
}

function handleOpenChange(open: boolean): void {
    if (!open) {
        emit('acknowledged');
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <div class="bg-primary/10 text-primary mb-2 flex size-10 items-center justify-center rounded-full">
                    <KeyRound class="size-5" />
                </div>
                <DialogTitle>Save sensor token now</DialogTitle>
                <DialogDescription> This token is shown once. Musikfestapp stores only its hash and cannot recover it later. </DialogDescription>
            </DialogHeader>

            <div class="grid gap-2">
                <div class="flex gap-2">
                    <Input :model-value="token" aria-label="Sensor API token" class="font-mono text-xs" readonly />
                    <Button aria-label="Copy sensor API token" size="icon" type="button" variant="outline" @click="copyToken">
                        <Copy class="size-4" />
                    </Button>
                </div>
                <p class="text-muted-foreground text-sm">Generating another token immediately invalidates this one.</p>
            </div>

            <DialogFooter>
                <Button type="button" @click="emit('acknowledged')">I have saved the token</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
