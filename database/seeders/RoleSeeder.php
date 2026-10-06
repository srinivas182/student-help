<?php

namespace Database\Seeders;

use App\Domains\Access\Models\Role;
use App\Domains\Access\Permissions;
use Illuminate\Database\Seeder;

/**
 * Starting roles. DX can edit these and create their own, except that system
 * roles cannot be deleted — losing the only role that can manage roles would
 * lock everyone out.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'Platform administrator',
                'platform-administrator',
                'Full access to everything except creating other administrators.',
                array_keys(Permissions::all()),
                true,
            ],
            [
                'Content reviewer',
                'content-reviewer',
                'Reviews, edits and approves AI Tutor lessons. No access to users, settings or private conversations.',
                ['topics.review', 'topics.publish'],
                true,
            ],
            [
                'Content author',
                'content-author',
                'Creates topics, uploads source material and generates lessons, but cannot approve them.',
                ['topics.manage', 'topics.generate'],
                true,
            ],
            [
                'Safeguarding moderator',
                'safeguarding-moderator',
                'Works the moderation queue, reviews conversations, groups and voice notes.',
                ['moderation.queue', 'moderation.conversations', 'moderation.groups', 'moderation.voice', 'users.suspend'],
                true,
            ],
            [
                'Tutor coordinator',
                'tutor-coordinator',
                'Verifies tutors, approves study material and school links.',
                ['tutors.verify', 'resources.review', 'classes.schoolLinks'],
                true,
            ],
        ];

        foreach ($roles as [$name, $slug, $description, $permissions, $system]) {
            Role::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => $description,
                    'permissions' => $permissions,
                    'is_system' => $system,
                ],
            );
        }
    }
}
