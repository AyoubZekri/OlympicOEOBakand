<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AlertsVersion;
use Illuminate\Http\Request;

/**
 * The alerts, with as little work as possible for the server:
 * - version: "has anything changed?", read from a file (no database), asked every few seconds;
 * - all: everything the alerts are made from, in one request (instead of one request per list).
 */
class AlertsController extends Controller
{
    /** The lists the alerts are made from (read only), the only ones "all" gives */
    private const SOURCES = [
        '/tasks',
        '/disciplinary/mine', '/disciplinary', '/disciplinary/officiating',
        '/training-sessions/mine', '/training-sessions', '/training-sessions/mine/notices',
        '/matches/mine', '/matches', '/matches/mine/notices',
        '/absences/mine', '/absences',
        '/meetings/mine', '/meetings', '/decisions', '/meetings/mine/notices',
        '/travels/mine', '/travels', '/travels/mine/notices',
        '/medical-records/mine', '/medical-records', '/medical-records/mine/notices',
        '/debts', '/payments/credit',
    ];

    public function version()
    {
        return response()->json(['version' => AlertsVersion::current()]);
    }

    /**
     * Several lists in one request: ?p={"key":"/path?query",…} → {"key":{"status":200,"data":…},…}.
     * Each list is answered by its own route (same rights, same data), all inside this one request.
     */
    public function all(Request $request)
    {
        $paths = json_decode((string) $request->query('p', '{}'), true);
        if (!is_array($paths)) {
            return response()->json(['message' => 'طلب غير صالح'], 422);
        }

        $router = app('router');
        $out = [];
        foreach (array_slice($paths, 0, 40, true) as $key => $path) {
            $route = parse_url((string) $path, PHP_URL_PATH);
            if (!in_array($route, self::SOURCES, true)) {
                $out[$key] = ['status' => 404, 'data' => null];
                continue;
            }
            parse_str((string) parse_url((string) $path, PHP_URL_QUERY), $query);

            $sub = Request::create('/api' . $route, 'GET', $query);
            $sub->headers->replace($request->headers->all());
            $sub->setUserResolver($request->getUserResolver());

            try {
                $response = $router->dispatch($sub);
                $out[$key] = ['status' => $response->getStatusCode(), 'data' => json_decode($response->getContent(), true)];
            } catch (\Throwable $e) {
                $out[$key] = ['status' => 500, 'data' => null];
            }
        }

        // Back to this request
        app()->instance('request', $request);

        return response()->json($out);
    }
}
