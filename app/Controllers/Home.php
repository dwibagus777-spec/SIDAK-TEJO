<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        return redirect()->to(site_url('login'));
    }
}
