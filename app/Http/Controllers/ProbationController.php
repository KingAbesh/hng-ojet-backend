<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use App\User;
use App\Slack;
use App\Probation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Http\Classes\ActivityTrait;

class ProbationController extends Controller
{

    use ActivityTrait;

    public function probate(Request $request){
        // Only admin can do this
        if(!Auth::user()->hasAnyRole(['admin', 'superadmin'])){
            return $this->ERROR('You dont have the permission to perform this action');
        }
        
        $validation = Validator::make($request->all(),[
            'user_id' => 'required',
            'reason' => 'nullable',
            'exit_on' => 'date_format:Y-m-d'
        ])->validate();

        if($validation->fails()) return $this->ERROR( $validation->errors());

        $exit_date = Carbon::now()->addDays(1);
        if($request->exit_on){
            if(Carbon::make($request->exit_on)->isPast()) return $this->ERROR('Exit date must be in the future', $validation->errors());
            $exit_date = $request->exit_on;
        } 
        
        $is_user = User::find($request->user_id);
        $is_on_probation = Probation::where('user_id', $request->user_id)->first();

        if(!$is_user)  return $this->ERROR('Specified user does not exist');
        if($is_user->hasAnyRole(['admin', 'superadmin'])) return $this->ERROR('An admin cannot go on probation');
        if($is_on_probation)      
        
        
        // Remove the user from All Stages
        Probation::insert(['user_id'=>$request->user_id, 'probated_by'=>Auth::user()->id, 'probation_reason'=>$request->reason ?? null, 'exit_on'=>$exit_date]);

        $this->logAdminActivity("probated " . $is_user->firstname . " " . $is_user->lastname . " (". $is_user->email .")");

            $slack_id =  $is_user->slack_id;
            $probChannel = env('SLACK_PROBATION', 'test-underworld');
                    
            Slack::removeFromChannel($slack_id, $is_user->stage);
            Slack::addToGroup($slack_id, $probChannel);
        return $this->SUCCESS('Probation successful');   
    }

    public function is_on_onprobation(int $user_id){
        $data = Probation::where('user_id', $user_id)->with('user:id,firstname,lastname,email')->with('probator:id,firstname,lastname,email')->first();

        
        
        if($data){
            $data['user']['profile_img'] = User::find($user_id)->profile->profile_img;
            // $data = $probation;
            $data['status'] = true;
        }else{
            $data['status'] = false;
        }

        // dd($data);
        
        return $this->SUCCESS($data["status"], $data);
    }

    public function unprobate_by_admin(Request $request){
        // Only admin can do this
        if(!Auth::user()->hasAnyRole(['admin', 'superadmin'])){
            return $this->ERROR('You dont have the permission to perform this action');
        }
        $query = Probation::where('user_id', $request->user_id)->first();
        if($query) {
            $query->delete();

            $user = User::find($request->user_id);

            $this->logAdminActivity("remove " . $user->firstname . " " . $user->lastname . " (". $user->email .") from probation");

            $slack_id =  $user->slack_id;
            $probChannel = env('SLACK_PROBATION', 'test-underworld');
                    
            Slack::removeFromGroup($slack_id, $probChannel);
            Slack::addToChannel($slack_id, $user->stage);

            return $this->SUCCESS('Successfully removed user from probation');
        }else{
            return $this->SUCCESS('Specified user is not on probations');
        }

    }

    public function unprobate_by_system(Request $request){
        // This action will be triggered by a schedular
        $today = Carbon::now()->startOfDay()->format('Y-m-d');
        // Probation::where('exit_on', '<=', $today)->delete();
        $probations = Probation::where('exit_on', '<=', $today)->get();

        foreach($probations as $probation){
            $user = User::find($probation->user_id);

            $this->logAdminActivity("" . $user->firstname . " " . $user->lastname . " (". $user->email .") was automatically removed from probation");

             $slack_id =  $user->slack_id;
            $probChannel = env('SLACK_PROBATION', 'test-underworld');
                    
            Slack::removeFromGroup($slack_id, $probChannel);
            Slack::addToChannel($slack_id, $user->stage);

        }

        $probations->delete();
    }

    public function unprobate_by_action(Request $request){
        // Not clear yet what action by the user should remove him from probation
    }


    public function list_probations(Request $request){
        // Only admin can do this
        if(!Auth::user()->hasAnyRole(['admin', 'superadmin'])){
            return $this->ERROR('You dont have the permission to perform this action');
        }
        $data = Probation::join('users', 'users.id', 'probations.user_id')
                        ->join('users AS probator', 'probator.id', 'probations.probated_by')
                        ->select(
                            'probations.*', DB::raw("CONCAT(users.firstname,' ',users.lastname) as name"),
                            DB::raw("CONCAT(probator.firstname,' ',probator.lastname) as probated_by")
                        )
                        ->get();
        return $this->SUCCESS('All probations retrieved', $data);
    }
}
