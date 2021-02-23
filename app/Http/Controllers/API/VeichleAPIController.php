<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Veichle;
use Illuminate\Support\Facades\Validator;

class VeichleAPIController extends Controller
{
    private $rules;
    private $massages;
    public function __construct()
    {
        $this->rules=$this->rules();
        $this->massages=$this->massages();
    }

    public function create(Request $request){
        $validtior = Validator::make($request->all(),$this->rules,$this->massages);
        try
        {
            if($validtior->fails())
            {
                return $this->sendError($validtior->errors(),401);
            }
            $veichle = new Veichle();
            $veichle->type = $request->input('data.type');
            $veichle->plateno = $request->input('data.plateno');
            $veichle->capacity = $request->input('data.capacity');
            $veichle->brand = $request->input('data.brand');
            $veichle->user_id = $request->input('data.id');
            //$veichle->img_license = $request->input('data.img_license');
            //$veichle->img_vehicle_license = $request->input('data.img_vehicle_license');
            $veichle->save();
        }
        catch (\ErrorException $e)
        {
            return $this->sendError($e->getMessage(),401);
        }

    }

    private function rules(){
        return[
            'type'=>'required',
            'plateno'=>'required',
            'capacity'=>'required|numeric',
            'brand'=>'required',
            'id'=>'required|exists:users,id',
        ];
    }
    private function massages(){
        return [
            'type.required'=>trans('validation.type_required'),
            'plateno.required'=>trans('validation.plateno_required'),
            'capacity.required'=>trans('validation.capacity_required'),
            'capacity.numeric'=>trans('validation.capacity_numeric'),
            'brand.required'=>trans('validation.capacity_numeric'),
            'id_required'=>trans('validation.id_required'),
            'id.exists'=>trans('validation.id_exists'),
        ];
    }
}
