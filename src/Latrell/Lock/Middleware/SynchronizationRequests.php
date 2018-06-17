<?php

namespace Latrell\Lock\Middleware;

use Closure;

class SynchronizationRequests
{

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request  $request
     * @param \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $key = $this->resolveRequestSignature($request);
        $lock = app('lock');

        try {
            if ($lock->acquire($key)) {
                $response = $next($request);
            }
        } finally {
            $lock->release($key);
        }

        return $response;
    }

    /**
     * Resolve request signature.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    protected function resolveRequestSignature($request)
    {
        return $request->fingerprint();
    }
}
