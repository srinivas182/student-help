<?php

namespace App\Domains\Access;

/**
 * Everything a role can be granted. Grouped the way DX thinks about the job,
 * not the way the database is laid out.
 */
class Permissions
{
    public const GROUPS = [
        'AI Tutor content' => [
            'topics.manage' => 'Create topics and upload source material',
            'topics.generate' => 'Generate lessons with AI',
            'topics.review' => 'Review and edit generated lessons',
            'topics.publish' => 'Approve and publish lessons to students',
        ],
        'Safeguarding' => [
            'moderation.queue' => 'Work the moderation queue',
            'moderation.conversations' => 'Read private conversations',
            'moderation.groups' => 'Review and close study groups',
            'moderation.voice' => 'Review voice notes',
            'users.suspend' => 'Suspend and reinstate users',
        ],
        'Tutors and material' => [
            'tutors.verify' => 'Approve and reject tutor applications',
            'resources.review' => 'Approve study material',
            'classes.schoolLinks' => 'Approve school links on classes',
        ],
        'Platform' => [
            'users.manage' => 'Search and manage all users',
            'staff.invite' => 'Invite administrators and moderators',
            'curriculum.manage' => 'Edit the curriculum',
            'announcements.manage' => 'Publish announcements',
            'settings.manage' => 'Change platform settings',
            'assistant.configure' => 'Configure the AI assistant and budgets',
            'audit.view' => 'View the audit log',
            'roles.manage' => 'Create roles and assign them',
        ],
    ];

    /** @return array<string, string> */
    public static function all(): array
    {
        return collect(self::GROUPS)->flatMap(fn ($group) => $group)->all();
    }

    public static function exists(string $permission): bool
    {
        return array_key_exists($permission, self::all());
    }
}
