<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import CrudFormPage from '@/components/crud/CrudFormPage.vue';
import { useTranslation } from '@/composables/useTranslation';
import { index as apiKeysIndex, store as storeApiKey } from '@/routes/api-keys';
import type { CrudSchema } from '@/types/crud';

defineProps<{
    crud: CrudSchema;
}>();

const { t } = useTranslation();

setLayoutProps({
    breadcrumbs: [
        {
            title: t('API keys'),
            href: apiKeysIndex(),
        },
        {
            title: t('Create :name', { name: t('API key') }),
        },
    ],
});
</script>

<template>
    <Head :title="t('Create :name', { name: t('API key') })" />

    <CrudFormPage
        :schema="crud"
        :action="storeApiKey.form()"
        :back-href="apiKeysIndex()"
        :title="t('Create :name', { name: t('API key') })"
        :description="
            t(
                'Choose a role to limit what the key can do. The key is shown only once.',
            )
        "
        :submit-label="t('Create :name', { name: t('API key') })"
    />
</template>
