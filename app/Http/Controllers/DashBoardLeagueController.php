<?php

namespace App\Http\Controllers;

use App\Models\Licence;
use App\Models\Competition;
use Illuminate\Http\Request;

class DashBoardLeagueController extends Controller
{
    public function stats()
    {
        //licence_count
        $licenceCount = Licence::where('league_id', request()->attributes->get('organisateur_id'))
            ->count();

        $competitionCount = Competition::where('league_id', request()->attributes->get('organisateur_id'))
            ->count();
    }
}
