<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import CrudFormPage from '@/components/crud/CrudFormPage.vue';
import { useTranslation } from '@/composables/useTranslation';
import { index as apiKeysIndex } from '@/routes/api-keys';
import type { CrudSchema } from '@/types/crud';

const { apiKey } = defineProps<{
    crud: CrudSchema;
    apiKey: {
        id: string | number;
        name: string;
        prefix: string;
        role_id: string | number | null;
        status: 'active' | 'expired' | 'revoked';
        last_used_at: string | null;
        expires_at: string | null;
        revoked_at: string | null;
        created_at: string | null;
    };
}>();

const { t } = useTranslation();

const statusLabels = {
    active: 'Active',
    expired: 'Expired',
    revoked: 'Revoked',
} as const;

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}

setLayoutProps({
    breadcrumbs: [
        {
            title: t('API keys'),
            href: apiKeysIndex(),
        },
        {
            title: t('View :name', { name: apiKey.name }),
        },
    ],
});
</script>

<template>
    <Head :title="t('View :name', { name: apiKey.name })" />

    <CrudFormPage
        :schema="crud"
        :back-href="apiKeysIndex()"
        :title="t('View :name', { name: apiKey.name })"
        :description="t('Review the key details and its usage.')"
        submit-label=""
        :initial-values="apiKey"
        :read-only="true"
    >
        <template #fields>
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-muted-foreground">{{ t('Key') }}</dt>
                    <dd class="font-mono text-xs">{{ apiKey.prefix }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">{{ t('Status') }}</dt>
                    <dd>{{ t(statusLabels[apiKey.status]) }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">{{ t('Last used') }}</dt>
                    <dd>{{ formatDate(apiKey.last_used_at) }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">{{ t('Created at') }}</dt>
                    <dd>{{ formatDate(apiKey.created_at) }}</dd>
                </div>
                <div v-if="apiKey.revoked_at">
                    <dt class="text-muted-foreground">{{ t('Revoked at') }}</dt>
                    <dd>{{ formatDate(apiKey.revoked_at) }}</dd>
                </div>
            </dl>
        </template>
    </CrudFormPage>
</template>
