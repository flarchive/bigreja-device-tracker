<?php

namespace Bigreja\DeviceTracker\Api\Controller;

use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Database\ConnectionInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ListSharedDevicesController implements RequestHandlerInterface
{
    protected ConnectionInterface $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $params = $request->getQueryParams();
        $query = $params['q'] ?? '';
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        // Subquery para obter UUIDs partilhados
        $sub = $this->db->table('user_devices')
            ->select('device_uuid')
            ->groupBy('device_uuid')
            ->havingRaw('COUNT(DISTINCT user_id) > 1');

        $mainQuery = $this->db->table('user_devices as d')
            ->join('users as u', 'u.id', '=', 'd.user_id')
            ->joinSub($sub, 'shared', function ($join) {
                $join->on('d.device_uuid', '=', 'shared.device_uuid');
            })
            ->select([
                'd.device_uuid',
                $this->db->raw('COUNT(DISTINCT d.user_id) as total_users'),
                $this->db->raw('GROUP_CONCAT(DISTINCT u.username ORDER BY d.last_seen_at DESC SEPARATOR ", ") as usernames'),
                $this->db->raw('MAX(d.last_seen_at) as last_seen_at'),
                $this->db->raw('MAX(d.last_ip) as last_ip'),
                $this->db->raw('MAX(d.user_agent) as user_agent')
            ])
            ->groupBy('d.device_uuid');

        if (!empty($query)) {
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $query);
            $mainQuery->where(function ($w) use ($escaped) {
                $w->where('d.device_uuid', 'like', "%{$escaped}%")
                  ->orWhere('u.username', 'like', "%{$escaped}%")
                  ->orWhere('d.last_ip', 'like', "%{$escaped}%");
            });
        }

        $totalRecords = (clone $mainQuery)->get()->count();
        $results = $mainQuery->orderBy('last_seen_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return new JsonResponse([
            'data' => $results,
            'meta' => [
                'total' => $totalRecords,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($totalRecords / $limit),
            ]
        ]);
    }
}