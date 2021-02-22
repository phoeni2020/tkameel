<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;

class APIkey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header('api-key');
        $user = User::where('api_token','=',$key)->get();
        if (empty($user->toArray())){
            return back();
        }
        return $next($request);
    }
}
