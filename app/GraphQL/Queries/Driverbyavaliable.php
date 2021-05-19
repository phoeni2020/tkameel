<?php

namespace App\GraphQL\Queries;

use App\Models\Driver;

class Driverbyavaliable
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        $driver = Driver::where('available','=','1')->get();
        $user = $driver[0]->user_id()->get();
        //$driver= $user[0];
        return $driver[0];

    }
}
