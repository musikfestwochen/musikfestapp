<script lang="ts" setup>
import { Input } from '@/components/ui/input';
import { useToast } from '@/components/ui/toast/use-toast';
import { Copy } from 'lucide-vue-next';

const props = defineProps<{
    token?: string;
}>();

const { toast } = useToast();

const handleCopyClick = async () => {
    if (props.token) {
        try {
            await navigator.clipboard.writeText(props.token);
            toast({
                title: 'Copied!',
                description: 'Token copied to clipboard.',
            });
        } catch {
            toast({
                title: 'Error',
                description: 'Failed to copy token.',
                variant: 'destructive',
            });
        }
    }
};
</script>

<template>
    <div class="relative w-full max-w-xs items-center">
        <Input :model-value="props.token || ''" autocomplete="off" class="pr-10" placeholder="No token" readonly type="text" />
        <span class="absolute inset-y-0 inset-e-0 flex cursor-pointer items-center justify-center px-2" @click="handleCopyClick">
            <Copy class="text-muted-foreground size-5" />
        </span>
    </div>
</template>
