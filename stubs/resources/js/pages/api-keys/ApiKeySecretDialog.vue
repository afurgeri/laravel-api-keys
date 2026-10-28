<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Check, Copy } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/composables/useTranslation';

type FlashedApiKey = {
    name: string;
    plain_text_key: string;
};

const page = usePage();
const { t } = useTranslation();

const open = ref(false);
const copied = ref(false);
const apiKey = ref<FlashedApiKey | null>(null);

watch(
    () => (page.flash as { apiKey?: FlashedApiKey }).apiKey,
    (flashed) => {
        if (!flashed) {
            return;
        }

        apiKey.value = flashed;
        copied.value = false;
        open.value = true;
    },
    { immediate: true },
);

async function copyKey(): Promise<void> {
    if (!apiKey.value) {
        return;
    }

    await navigator.clipboard.writeText(apiKey.value.plain_text_key);
    copied.value = true;
}

function handleOpenChange(value: boolean): void {
    open.value = value;

    if (!value) {
        apiKey.value = null;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent v-if="apiKey">
            <DialogHeader class="space-y-3">
                <DialogTitle>{{ t('Copy your API key') }}</DialogTitle>
                <DialogDescription>
                    {{
                        t(
                            'This is the only time the key for :name will be shown. Store it in a safe place: if you lose it, rotate the key to get a new one.',
                            { name: apiKey.name },
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="flex items-center gap-2">
                <Input
                    :model-value="apiKey.plain_text_key"
                    readonly
                    class="font-mono text-xs"
                    :aria-label="t('API key')"
                    @focus="($event.target as HTMLInputElement).select()"
                />
                <Button
                    type="button"
                    variant="secondary"
                    size="icon"
                    :aria-label="t('Copy')"
                    @click="copyKey"
                >
                    <component :is="copied ? Check : Copy" class="size-4" />
                </Button>
            </div>

            <DialogFooter>
                <Button type="button" @click="handleOpenChange(false)">
                    {{ t('I have saved the key') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
