<?php

namespace App\Http\Controllers;

use App\Support\HomeRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Хуучин «Холбосон системүүд» хаб — хэрэглэгчийн UI-аас хасагдсан.
     */
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route(HomeRedirect::routeName($request->user()));
    }
}
