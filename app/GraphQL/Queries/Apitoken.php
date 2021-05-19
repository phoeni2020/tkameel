<?php

namespace App\GraphQL\Queries;

use App\Models\User;

class Apitoken
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        $data = User::where('api_token','=',$args['api_token'])->get();
        $user = $data->toArray();
        return $user[0];
    }
}
