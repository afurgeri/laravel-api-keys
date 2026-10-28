<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { Ban, RefreshCw } from '@lucide/vue';
import CrudDeleteDialog from '@/components/crud/CrudDeleteDialog.vue';
import CrudPage from '@/components/crud/CrudPage.vue';
import { useTranslation } from '@/composables/useTranslation';
import ApiKeySecretDialog from '@/pages/api-keys/ApiKeySecretDialog.vue';
import {
    create as createApiKey,
    destroy as revokeApiKey,
    index as apiKeysIndex,
    rotate as rotateApiKey,
    show as showApiKey,
    store as storeApiKey,
} from '@/routes/api-keys';
import type { CrudPaginator, CrudRecord, CrudSchema } from '@/types/crud';

type ApiKey = CrudRecord & {
    id: string | number;
    name: string;
    prefix: string;
    status: 'active' | 'expired' | 'revoked';
    can: {
        show: boolean;
        update: boolean;
        rotate: boolean;
        delete: boolean;
    };
};

defineProps<{
    crud: CrudSchema;
    apiKeys: CrudPaginator<ApiKey>;
    can: {
        create: boolean;
    };
}>();

/**
 * The route key is numeric for SQL storage and a string for MongoDB.
 */
type ApiKeyRouteKey = Parameters<typeof showApiKey>[0];

function routeKey(record: ApiKey): ApiKeyRouteKey {
    return record.id as ApiKeyRouteKey;
}

const { t } = useTranslation();

setLayoutProps({
    breadcrumbs: [
        {
            title: t('API keys'),
            href: apiKeysIndex(),
        },
    ],
});
</script>

<template>
    <div>
        <Head :title="crud.title" />

        <CrudPage
            :schema="crud"
            :records="apiKeys"
            :locked-label="t('Revoked')"
            :create="{
                can: can.create,
                href: createApiKey(),
                action: storeApiKey.form(),
                label: t('Create :name', { name: t('API key') }),
                title: t('Create :name', { name: t('API key') }),
                description: t(
                    'Choose a role to limit what the key can do. The key is shown only once.',
                ),
            }"
            :edit="{ can: () => false, action: () => apiKeysIndex.form() }"
            :show="{
                href: (record) => showApiKey(routeKey(record)),
                can: (record) => record.can.show,
                title: (record) => t('View :name', { name: record.name }),
            }"
            :destroy="{
                action: (record) => revokeApiKey.form.delete(routeKey(record)),
                can: (record) => record.can.delete,
                label: t('Revoke'),
                icon: Ban,
                title: (record) => t('Revoke :name?', { name: record.name }),
                description: t(
                    'Requests using this key will be rejected immediately. This action cannot be undone.',
                ),
            }"
        >
            <template #cell-name="{ value }">
                <span class="font-medium">{{ value }}</span>
            </template>

            <template #cell-prefix="{ value }">
                <span class="font-mono text-xs">{{ value }}</span>
            </template>

            <template #actions-before="{ record }">
                <CrudDeleteDialog
                    v-if="record.can.rotate"
                    :action="rotateApiKey.form(routeKey(record))"
                    :trigger-label="t('Rotate')"
                    :title="t('Rotate :name?', { name: record.name })"
                    :description="
                        t(
                            'A new key will be generated and the current one will stop working immediately.',
                        )
                    "
                    :icon="RefreshCw"
                />
            </template>
        </CrudPage>

        <ApiKeySecretDialog />
    </div>
</template>
