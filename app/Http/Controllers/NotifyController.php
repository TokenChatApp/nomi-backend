<?php

namespace App\Http\Controllers;

require '../vendor/autoload.php';

use App\Settings;
use App\User;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;

class NotifyController extends Controller
{
    public function __construct()
    {

    }

    public function send(Request $request)
    {
        $date = new DateTime;
        $date->modify('-15 minutes');
        $formatted_date = $date->format('Y-m-d H:i:s');

        // get Males
        $users = User::select('*')
                     ->where('gender', 'M')
                     ->where(function ($query) use ($formatted_date) {
                        $query->whereNull('last_action')
                              ->orWhere('last_action', '<=', $formatted_date);
                     })                     
                     ->get();
        
        if ($users != null) {
            foreach ($users as $u) {
                echo $u->username.'<br/>';
            }
        }

        // get Females
        $users = User::select('*')
                     ->where('gender', 'F')
                     ->whereNull('last_action')
                     ->orWhere('last_action', '<=', $formatted_date)
                     ->get();
        
        if ($users != null) {
            foreach ($users as $u) {
                echo $u->username.'<br/>';
            }
        }
    }

}