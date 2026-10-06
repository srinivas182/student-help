export interface User {
    id: number;
    name: string;
    first_name?: string;
    last_name?: string;
    email: string;
    email_verified_at?: string;
    role: 'student' | 'tutor' | 'moderator' | 'admin' | 'super_admin';
    can_participate?: boolean;
    is_minor?: boolean;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
};
