<?php

namespace App\Http\Controllers;

use App\Support\HelpCenter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuideController
{
    public function __invoke(Request $request, HelpCenter $helpCenter): View
    {
        return view('guide.index', [
            'introduction' => $helpCenter->roleIntroduction($request->user()),
            'sections' => $helpCenter->sections($request->user()),
        ]);
    }
}
