<?php

namespace App\Controllers;

use Core\Http\Controllers\Controller;
use Lib\Authentication\Auth;

class HomeController extends Controller
{
    public function index(): void
    {
        if (Auth::check()) {
            $this->redirectTo(route('dashboard.index'));
            return;
        }

        $title = 'Home Page';
        $this->render('home/index', compact('title'));
    }
}
