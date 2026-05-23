<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\AthleteProfiles;
use App\Models\Coach;
use App\Models\ClubProfile;

class PreviewController extends Controller
{
    use ApiResponse;
    public function athleteProfile(Request $request)
    {
        $validator=Validator::make($request->all(),[
            'athlete_id'=>'required|exists:athlete_profiles,id'
        ]);
        if($validator->fails()){
            return $this->validationError($validator->errors(),'Validation failed',422);
        }


    try{

        $athlete=AthleteProfiles::query()->where('id',request()->input('athlete_id'))->first();
        if(!$athlete){
            return $this->ValidationError([],'Athlete not found',422);
        }

       if ($request->hasFile('preview')) {

           if($athlete->preview && file_exists(public_path($athlete->preview))){

                unlink(public_path($athlete->preview));
           }

           $file = $request->file('preview');
           $extensiion = $file->getClientOriginalExtension();
           $file_name = time() . '.' . $extensiion;
           $path="uploads/preview";
           $file->move(public_path($path), $file_name);
              $athlete->preview = $path.$file_name;
        }

                $athlete->save();
                return $this->success($athlete,'Preview updated successfully',200);

            }
    catch (\Throwable $e){
        return $this->errors([],$e->getMessage(),500);
    }
    }

    public function coachProfile(Request $request)
    {

            $validator=Validator::make($request->all(),[
            'coach_id'=>'required|exists:coaches,id'
        ]);
        if($validator->fails()){
            return $this->validationError($validator->errors(),'Validation failed',422);
        }


    try{

        $coach=Coach::query()->where('id',request()->input('coach_id'))->first();
        if(!$coach){
            return $this->ValidationError([],'Coach not found',422);
        }

       if ($request->hasFile('preview')) {


            if ($coach->preview && file_exists(public_path($coach->preview))) {

                unlink(public_path($coach->preview));
            }

            $file = $request->file('preview');


            $file_name = time() . '.' . $file->getClientOriginalExtension();

            $destinationPath = public_path('uploads/preview');


            if (!file_exists($destinationPath)) {

                mkdir($destinationPath, 0755, true);
            }


            $file->move($destinationPath, $file_name);


            $coach->preview = 'uploads/preview/' . $file_name;
        }

                $coach->save();
                return $this->success($coach,'Preview updated successfully',200);

            }
    catch (\Throwable $e){
        return $this->errors([],$e->getMessage(),500);
    }

    }

    public function clubProfile(Request $request)
    {

            $validator=Validator::make($request->all(),[
            'club_id'=>'required|exists:club_profiles,id'
        ]);
        if($validator->fails()){
            return $this->validationError($validator->errors(),'Validation failed',422);
        }


    try{

        $club=ClubProfile::query()->where('id',request()->input('club_id'))->first();
        if(!$club){
            return $this->ValidationError([],'Club not found',422);
        }

       if ($request->hasFile('preview')) {


            if ($club->preview && file_exists(public_path($club->preview))) {

                unlink(public_path($club->preview));
            }

            $file = $request->file('preview');


            $file_name = time() . '.' . $file->getClientOriginalExtension();

            $destinationPath = public_path('uploads/preview');


            if (!file_exists($destinationPath)) {

                mkdir($destinationPath, 0755, true);
            }


            $file->move($destinationPath, $file_name);


            $club->preview = 'uploads/preview/' . $file_name;
        }

                $club->save();
                return $this->success($club,'Preview updated successfully',200);

            }
    catch (\Throwable $e){
        return $this->errors([],$e->getMessage(),500);
    }

    }
}
