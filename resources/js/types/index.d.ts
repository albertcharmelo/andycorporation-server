import type { PageProps } from '@inertiajs/core';
import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface SharedData extends PageProps {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    tel?: string;
    cedula_type?: string;
    cedula_ID?: string;
    roles?: string[];
    is_admin?: boolean;
}

export interface Role {
    id: number;
    name: string;
    guard_name: string;
}

export interface Order {
    id: number;
    user_id: number;
    address_id: number | null;
    subtotal: string;
    shipping_cost: string;
    total: string;
    payment_method: 'manual_transfer' | 'mobile_payment' | 'credit_card' | 'paypal' | 'binance';
    payment_reference: string | null;
    status: 'pending_payment' | 'paid' | 'shipped' | 'completed' | 'cancelled' | 'refunded';
    notes: string | null;
    created_at: string;
    updated_at: string;
    user?: User;
    address?: UserAddress;
    items?: OrderItem[];
    payment_proof?: PaymentProof;
}

export interface OrderItem {
    id: number;
    order_id: number;
    product_id: number;
    product_name: string;
    quantity: number;
    price_at_purchase: string;
    created_at: string;
    updated_at: string;
    product?: {
        id: number;
        name: string;
        price: string;
        sku?: string;
    };
}

export interface UserAddress {
    id: number;
    user_id: number;
    address_line_1: string;
    address_line_2: string | null;
    postal_code: string;
    name: string;
    referencia: string | null;
    latitude: number;
    longitude: number;
    is_default: boolean;
    created_at: string;
    updated_at: string;
}

export interface PaymentProof {
    id: number;
    order_id: number;
    file_path: string;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

export interface OrderStatistics {
    stats: {
        total_orders: number;
        pending_payment: number;
        paid: number;
        shipped: number;
        completed: number;
        cancelled: number;
        refunded: number;
        total_revenue: string;
        pending_revenue: string;
    };
    payment_methods: Array<{
        payment_method: string;
        count: number;
    }>;
    last_7_days: Array<{
        date: string;
        count: number;
        revenue: string;
    }>;
}

export type BreadcrumbItemType = BreadcrumbItem;
