<?php

namespace App\Http\Middleware;

use App\Services\Alerts\AlertPusher;
use App\Services\AlertsVersion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Every change that went through (not a read, not an error) raises the alerts' version */
class BumpAlertsVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        // Raised at most once per request (whatever it saves), then free again for the next one
        AlertsVersion::reset();
        $response = $next($request);

        if (!$request->isMethodSafe() && $response->getStatusCode() < 400) {
            AlertsVersion::bump();
            // What it caused (an absence logged, a status changed, a reply…) reaches the phones right after it,
            // not at the next cron
            AlertPusher::afterSave($request);
        }
        AlertsVersion::reset();

        return $response;
    }
}
