<?php

namespace SuperBraga\DeviceTracker\Notification;

use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\User\User;

class DuplicateAccountAlertBlueprint implements BlueprintInterface
{
    public User $actor;
    public array $linkedUsernames;
    public string $deviceUuid;

    public function __construct(User $actor, array $linkedUsernames, string $deviceUuid)
    {
        $this->actor = $actor;
        $this->linkedUsernames = $linkedUsernames;
        $this->deviceUuid = $deviceUuid;
    }

    public function getFromUser(): ?User
    {
        return $this->actor;
    }

    public function getSubject(): ?User
    {
        return $this->actor;
    }

    public function getData(): array
    {
        return [
            'actor_id'         => $this->actor->id,
            'actor_username'   => $this->actor->username,
            'linked_usernames' => $this->linkedUsernames,
            'device_uuid'      => $this->deviceUuid,
        ];
    }

    public static function getType(): string
    {
        return 'duplicateAccountDetected';
    }

    public static function getSubjectModel(): string
    {
        return User::class;
    }
}