<?php

namespace App\Lib;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;

class Intended {
    public static function identifyRoute(){
        if(session()->get('intended_validation_error')){
            return false;
        }
        $intendedUrls = config('intended_routes');

        // The previous URL can be external, or a path that matches no route.
        // match() throws in that case, which would break the login page, so a
        // failure here simply means "no intended destination".
        try {
            $previousRouteName = Route::getRoutes()
                ->match(request()->create(url()->previousPath()))
                ->getName();
        } catch (\Throwable $e) {
            session()->forget('intended_info');
            return false;
        }
        if (array_key_exists($previousRouteName, $intendedUrls)) {
            $previousUrl = url()->previous();
            $previousUrlParts = parse_url($previousUrl);
            $queryString = isset($previousUrlParts['query']) ? $previousUrlParts['query'] : '';
            parse_str($queryString, $queryParams);
            $redirectRouteName = $previousRouteName;
            $redirectRouteUrl = $previousUrl;
            try{
                if($intendedUrls[$previousRouteName]){
                    $redirectRouteName = $intendedUrls[$previousRouteName];
                    $redirectRouteUrl = route($redirectRouteName);
                }
            }catch(\Exception $error){
                throw new \Exception("Intended route [$redirectRouteName] not defined");
            }
            $data['route_name'] = $redirectRouteName;
            $data['route_full_url'] = $redirectRouteUrl;
            $data['query_params'] = $queryParams;
            $data['form_data'] = request()->all();
            self::assignSession($data);
        } else {
            session()->forget('intended_info');
        }
    }

    public static function assignSession($data){
        session()->put('intended_info', $data);
    }

    public static function reAssignSession(){
        $data = session()->get('intended_info');
        if($data){
            self::assignSession($data);
        }
        Session::flash('intended_validation_error', 1);
    }

    public static function getRedirection(){
        if (session()->has('intended_info')) {
            $url = session('intended_info');
            session()->forget('intended_info');
            return redirect()->to($url['route_full_url']);
        }
        return false;
    }
}
