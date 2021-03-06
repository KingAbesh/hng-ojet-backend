<?php

namespace App\Http\Controllers;
use App\Task;
use App\User;
use App\TrackUser;
use App\TaskSubmission;
use DB;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class LeaderboardController extends Controller
{
    public function viewAll($track = 0){
        $users = User::where('role', '=', 'intern')
                ->with('tracks')
                ->get();

        // $users = User::where('role', '=', 'intern')
        //         ->join('track_users', 'track_users.user_id', '=', 'users.id')
        //         ->join('tracks', 'tracks.id', '=', 'track_users.id')
        //         // ->with('tracks')
        //         ->select('*')->get();

        // $users = DB::table('users')
        //         // ->select('users.*')
        //         ->join('track_users', 'track_users.user_id', '=', 'users.id')
        //         ->join('tracks', 'track_users.track_id', '=', 'tracks.id')
        //         ->where('users.role', '=', 'intern')
        //         ->where('tracks.id', $track)
        //         ->distinct()
        //         ->get();

        // $users = TrackUser::where('track_id', $track)
        //         ->join('users')
        //         ->get();

        // return $users;

        $res = [];

        foreach($users as $user){
            $uid = $user->id;
            // dd($uid);
            $user->total_score = $this->totalScoreForWeek($uid);
            $track_name = '';
            foreach($user->tracks as $sub){
                $track_name  = $track_name . "". $sub->track_name. ", ";
            }
            $user['all_tracks'] = $track_name;
            $user['tracks'] = [];
            array_push($res, $user);
        // return $res;

        }

        // return $res;
        // return $this->sort_array_of_array($res, 'total_score');

        return $this->paginate($res, 15);
    }

    function sort_array_of_array(&$array, $subfield)
{
    $sortarray = array();
    foreach ($array as $key => $row)
    {

        dd($row[$subfield]);
        $sortarray[$key] = $row[$subfield];
    }

    array_multisort($sortarray, SORT_ASC, $array);
}

    function invenDescSort($item1,$item2)
    {
        if ($item1['price'] == $item2['price']) return 0;
        return ($item1['price'] < $item2['price']) ? 1 : -1;
    }

    public function paginate($items, $perPage = 5, $page = null, $options = [])
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);

        return new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
    }

    public function totalScoreForWeek($user_id){
        $db = DB::table('task_submissions')
            ->where('user_id', $user_id)
            ->sum('grade_score');
        return round($db, 2);
            
        // $score = 0;
        // foreach($db as $s){
        //     $score += $s->grade_score;
        // }
        // return (int)$score;
    }

}