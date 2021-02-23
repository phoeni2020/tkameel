<?php
/**
 * File name: UserAPIController.php
 * Last modified: 2020.10.29 at 17:03:54
 * Author: SmarterVision - https://codecanyon.net/user/smartervision
 * Copyright (c) 2020
 *
 */

namespace App\Http\Controllers\API\Driver;

use App\Events\UserRoleChangedEvent;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\CustomFieldRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UploadRepository;
use App\Repositories\UserRepository;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Prettus\Validator\Exceptions\ValidatorException;

class UserAPIController extends Controller
{
    private $userRepository;
    private $uploadRepository;
    private $roleRepository;
    private $customFieldRepository;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(UserRepository $userRepository, UploadRepository $uploadRepository, RoleRepository $roleRepository, CustomFieldRepository $customFieldRepo)
    {
        $this->userRepository = $userRepository;
        $this->uploadRepository = $uploadRepository;
        $this->roleRepository = $roleRepository;
        $this->customFieldRepository = $customFieldRepo;
    }

    function login(Request $request)
    {
        try {
            $this->validate($request, [
                'data.phone' => 'required',
                'data.password' => 'required',
            ]);
            if (auth()->attempt(['phone' => $request->input('data.phone'), 'password' => $request->input('data.password')])) {
                // Authentication passed...
                $user = auth()->user();
                $user->device_token = $request->input('device_token');
                $user->save();
                $data = $user->toArray();
                $info =[
                    'id'=>$data['id'],
                    'name'=>$data['name'],
                    'email'=>$data['email'],
                    'phone'=>$data['phone'],
                    'api_token'=>$data['api_token'],
                    'image'=>$data['media'][0]['url']
                ];
                return $this->sendResponse($info, 'User retrieved successfully');
            }
        }
        catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 401);
        }

    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param array $data
     * @return
     */

    function register(Request $request)
    {
        try {
            $this->validate($request, [
                'data.name' => 'required',
                'data.country_code'=>'required',
                'data.key'=>'required',
                'data.phone' => 'required|unique:users,phone',
                'data.password' => 'required',
            ]);
            //API key
            $access_key = $this->access_key;

            $number = $request->input('data.phone');

            $country = $request->input('data.country_code');

            /**
             * This is api to check phone number
             * and make sure the phone number is a real number
             *
             * in case the phone number is a real phone num
             * the response will be like
            {
            "valid": true,
            "number": "14158586273",
            "local_format": "4158586273",
            "international_format": "+14158586273",
            "country_prefix": "+1",
            "country_code": "US",
            "country_name": "United States of America",
            "location": "Novato",
            "carrier": "AT&T Mobility LLC",
            "line_type": "mobile"
            }
             * else
            {
            "valid": false,
            "number": "14158586273",
            "local_format": "",
            "international_format": "",
            "country_prefix": "",
            "country_code": "",
            "country_name": "",
            "location": "",
            "carrier": "",
            "line_type": ""
            }

             * if there is any config errors the response will be like
             *
            {
            "success": false,
            "error": {
            "code": 210,
            "type": "no_phone_number_provided",
            "info": "Please specify a phone number. [Example: 14158586273]"
            }
            }
             *
             * Edit @ 2021.02.21 12:09
             * By Khaled waleed
             */

            $url =
                "http://apilayer.net/api/validate?access_key=${access_key}&number=${number}&country_code=${country}";

            $http = new Client();

            $response = $http->get("$url");

            $body = $response->getBody()->getContents();

            $response_arr = json_decode($body,true);

            if(isset($response_arr['success']) && $response_arr['success'] == false){
                return $this->sendError($response_arr['error']['info'],$response_arr['error']['code']);
            }
            elseif (isset($response_arr['valid']) && $response_arr['valid'] == false ){
                return $this->sendError('Sorry The Number You Have Entered Is Not A Valid Or Real Number In Your Region',404);
            }

            $user = new User();
            $user->name = $request->input('data.name');
            $user->phone = $request->input('data.key').$request->input('data.phone');
            $user->email = $request->input('device_token').'@tkameel.com';
            $user->device_token = $request->input('device_token');
            $user->password = Hash::make($request->input('data.password'));
            $user->api_token = str_random(60);
            $user->save();

            $user->assignRole('client');

            event(new UserRoleChangedEvent($user));

            if(copy(public_path('images/avatar_default.png'),public_path('images/avatar_default_temp.png'))) {
                $user->addMedia(public_path('images/avatar_default_temp.png'))
                    ->withCustomProperties(['uuid' => bcrypt(str_random())])
                    ->toMediaCollection('avatar');
            }
            $data = $user->toArray();
            $info =[
                'id'=>$data['id'],
                'name'=>$data['name'],
                'email'=>$data['email'],
                'phone'=>$data['phone'],
                'api_token'=>$data['api_token'],
                'image'=>$data['media'][0]['url']
            ];
        }
        catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 401);
        }

        return $this->sendResponse($info, 'User retrieved successfully');
    }

    function logout(Request $request)
    {
        $user = $this->userRepository->findByField('api_token', $request->input('api_token'))->first();
        if (!$user) {
            return $this->sendError('User not found', 401);
        }
        try {
            auth()->logout();
        } catch (\Exception $e) {
            $this->sendError($e->getMessage(), 401);
        }
        return $this->sendResponse($user['name'], 'User logout successfully');

    }

    function user(Request $request)
    {
        $user = $this->userRepository->find($request->input('id'))->first();

        if (!$user) {
            return $this->sendError('User not found', 401);
        }

        return $this->sendResponse($user, 'User retrieved successfully');
    }

    function settings(Request $request)
    {
        $settings = setting()->all();
        $settings = array_intersect_key($settings,
            [
                'default_tax' => '',
                'default_currency' => '',
                'default_currency_decimal_digits' => '',
                'app_name' => '',
                'currency_right' => '',
                'enable_paypal' => '',
                'enable_stripe' => '',
                'enable_razorpay' => '',
                'main_color' => '',
                'main_dark_color' => '',
                'second_color' => '',
                'second_dark_color' => '',
                'accent_color' => '',
                'accent_dark_color' => '',
                'scaffold_dark_color' => '',
                'scaffold_color' => '',
                'google_maps_key' => '',
                'fcm_key' => '',
                'mobile_language' => '',
                'app_version' => '',
                'enable_version' => '',
                'distance_unit' => '',
            ]
        );

        if (!$settings) {
            return $this->sendError('Settings not found', 401);
        }

        return $this->sendResponse($settings, 'Settings retrieved successfully');
    }

    /**
     * Update the specified User in storage.
     *
     * @param int $id
     * @param Request $request
     *
     */
    public function update($id, Request $request)
    {
        $user = $this->userRepository->findWithoutFail($id);

        if (empty($user)) {
            return $this->sendResponse([
                'error' => true,
                'code' => 404,
            ], 'User not found');
        }
        $input = $request->except(['password', 'api_token']);
        try {
            if ($request->has('device_token')) {
                $user = $this->userRepository->update($request->only('device_token'), $id);
            } else {
                $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->userRepository->model());
                $user = $this->userRepository->update($input, $id);

                foreach (getCustomFieldsValues($customFields, $request) as $value) {
                    $user->customFieldsValues()
                        ->updateOrCreate(['custom_field_id' => $value['custom_field_id']], $value);
                }
            }
        } catch (ValidatorException $e) {
            return $this->sendError($e->getMessage(), 401);
        }

        return $this->sendResponse($user, __('lang.updated_successfully', ['operator' => __('lang.user')]));
    }

    function sendResetLinkEmail(Request $request)
    {
        $this->validate($request, ['email' => 'required|email']);

        $response = Password::broker()->sendResetLink(
            $request->only('email')
        );

        if ($response == Password::RESET_LINK_SENT) {
            return $this->sendResponse(true, 'Reset link was sent successfully');
        } else {
            return $this->sendError([
                'error' => 'Reset link not sent',
                'code' => 401,
            ], 'Reset link not sent');
        }

    }
}
