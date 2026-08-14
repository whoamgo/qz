<?php

namespace App\Http\Controllers;

abstract class Controller
{
    public function __construct()
    {
        // Vendor licence gate removed: it attached the remote check-project
        // middleware to every controller in the application.
    }

    public static function middleware()
    {
        return [];
    }

}
