<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';
import { computed } from 'vue';

interface Props {
    user: User;
    showEmail?: boolean;
    showRole?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
    showRole: false,
});

const { getInitials } = useInitials();

// Compute whether we should show the avatar image
const showAvatar = computed(() => props.user.avatar && props.user.avatar !== '');

// Get user's primary role with proper formatting
const userRole = computed(() => {
    if (!props.user.roles || props.user.roles.length === 0) {
        return 'Cliente';
    }

    const role = props.user.roles[0];
    const roleMap: Record<string, string> = {
        'super_admin': 'Super Administrador',
        'admin': 'Administrador',
        'delivery': 'Repartidor',
        'client': 'Cliente',
    };

    return roleMap[role] || role.charAt(0).toUpperCase() + role.slice(1);
});
</script>

<template>
    <Avatar class="h-8 w-8 overflow-hidden rounded-lg">
        <AvatarImage v-if="showAvatar" :src="user.avatar" :alt="user.name" />
        <AvatarFallback class="rounded-lg text-black dark:text-white">
            {{ getInitials(user.name) }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-medium">{{ user.name }}</span>
        <span v-if="showRole" class="truncate text-xs font-semibold" style="color: #0b3c87;">
            {{ userRole }}
        </span>
        <span v-if="showEmail" class="truncate text-xs text-muted-foreground">{{ user.email }}</span>
    </div>
</template>
