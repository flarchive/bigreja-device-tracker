<?php

namespace Bigreja\DeviceTracker\Middleware;

use Carbon\Carbon;
use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\SetCookie;
use Flarum\Group\Group;
use Flarum\Http\RequestUtil;
use Flarum\Notification\NotificationSyncer;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;
use Bigreja\DeviceTracker\Notification\DuplicateAccountAlertBlueprint;

class TrackDeviceMiddleware implements MiddlewareInterface
{
    protected ConnectionInterface $db;
    protected NotificationSyncer $notifications;
    const COOKIE_NAME = 'sb_device_id';

    public function __construct(ConnectionInterface $db, NotificationSyncer $notifications)
    {
        $this->db = $db;
        $this->notifications = $notifications;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $cookies = $request->getCookieParams();
        $deviceUuid = $cookies[self::COOKIE_NAME] ?? null;
        $isNewDevice = false;

        if (!$deviceUuid || !Uuid::isValid($deviceUuid)) {
            $deviceUuid = Uuid::uuid4()->toString();
            $isNewDevice = true;
        }

        $actor = RequestUtil::getActor($request);

        if ($actor && !$actor->isGuest()) {
            $ip = $request->getHeaderLine('CF-Connecting-IP') 
                  ?: ($request->getServerParams()['REMOTE_ADDR'] ?? null);
            $ua = substr($request->getHeaderLine('User-Agent'), 0, 255);

            // Verifica se o par (user_id, device_uuid) já existia
            $existingAssociation = $this->db->table('user_devices')
                ->where('user_id', $actor->id)
                ->where('device_uuid', $deviceUuid)
                ->exists();

            // Atualiza ou insere o registo
            $this->db->table('user_devices')->upsert(
                [
                    'user_id'       => $actor->id,
                    'device_uuid'   => $deviceUuid,
                    'last_ip'       => $ip,
                    'user_agent'    => $ua,
                    'last_seen_at'  => Carbon::now()
                ],
                ['user_id', 'device_uuid'],
                ['last_ip', 'user_agent', 'last_seen_at']
            );

            // Se for a primeira vez que esta conta usa este UUID, verifica colisões
            if (!$existingAssociation) {
                $linkedUsers = $this->db->table('user_devices')
                    ->join('users', 'users.id', '=', 'user_devices.user_id')
                    ->where('user_devices.device_uuid', $deviceUuid)
                    ->where('user_devices.user_id', '!=', $actor->id)
                    ->pluck('users.username')
                    ->toArray();

                if (!empty($linkedUsers)) {
                    $this->notifyModerators($actor, $linkedUsers, $deviceUuid);
                }
            }
        }

        $response = $handler->handle($request);

        if ($isNewDevice || !isset($cookies[self::COOKIE_NAME])) {
            $response = FigResponseCookies::set(
                $response,
                SetCookie::create(self::COOKIE_NAME)
                    ->withValue($deviceUuid)
                    ->withExpires(Carbon::now()->addYear()->toDateTimeString())
                    ->withPath('/')
                    ->withHttpOnly(true)
                    ->withSecure(true)
                    ->withSameSite(\Dflydev\FigCookies\Modifier\SameSite::lax())
            );
        }

        return $response;
    }

    protected function notifyModerators(User $actor, array $linkedUsers, string $deviceUuid): void
    {
        // Obtém moderadores e administradores
        $moderators = User::whereHas('groups', function ($query) {
            $query->whereIn('id', [Group::ADMINISTRATOR_ID, Group::MODERATOR_ID]);
        })->get();

        $blueprint = new DuplicateAccountAlertBlueprint($actor, $linkedUsers, $deviceUuid);
        $this->notifications->sync($blueprint, $moderators->all());
    }
}